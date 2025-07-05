<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');


// cron.php — Automatyczne sprawdzanie domen z uwzględnieniem ustawień i alertów
require 'db.php'; // Połączenie z bazą danych

// Pobieramy ustawienia systemowe (interwał dni i próg procentowy alertów)
$stmt = $conn->prepare("SELECT check_interval_days, alert_threshold_percent FROM settings LIMIT 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$default_interval = $settings['check_interval_days'];
$default_threshold = $settings['alert_threshold_percent'];

// Wybieranie jednej domeny do sprawdzenia z uwzględnieniem ustawień domeny
$stmt = $conn->prepare("
    SELECT d.id, d.domain, d.user_id, d.competitor,
           COALESCE(d.check_interval_days, ?) AS interval_days,
           COALESCE(d.alert_threshold_percent, ?) AS user_threshold,
           MAX(dc.checked_at) AS last_checked,
           (SELECT result FROM domain_checks WHERE domain_id = d.id ORDER BY checked_at DESC LIMIT 1) AS last_check
    FROM domains d
    LEFT JOIN domain_checks dc ON d.id = dc.domain_id
    GROUP BY d.id
    HAVING last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL interval_days DAY)
    ORDER BY last_checked ASC, d.id ASC
    LIMIT 1
");
$stmt->bind_param('ii', $default_interval, $default_threshold);
$stmt->execute();
$result = $stmt->get_result();
$domain = $result->fetch_assoc();

if ($domain) {
    $domain_id = $domain['id'];
    $domain_url = $domain['domain'];
    $user_id = $domain['user_id'];
    $competitor = $domain['competitor'];
    $last_check = $domain['last_check'];
    $check_interval_days = $domain['interval_days'];
    $alert_threshold_percent = $domain['user_threshold'];

    // Liczymy liczbę URL-i w sitemapie
    $url_count = countUrlsInSitemap($domain_url);

    if ($url_count !== false) {
        // Zapisujemy wynik sprawdzenia do bazy danych
        $stmt = $conn->prepare("INSERT INTO domain_checks (domain_id, result) VALUES (?, ?)");
        $stmt->bind_param('ii', $domain_id, $url_count);
        $stmt->execute();

        echo "Sprawdzono domenę: {$domain_url} (Liczba URL-i: {$url_count})\n";

        // Zapisanie sitemapy na serwerze
        saveSitemapToFile($domain_url, $domain_id, $user_id);

        // Sprawdzenie, czy liczba URL-i zmieniła się o więcej niż próg procentowy (jeśli nie jest domeną konkurencyjną)
        if ($competitor == 0 && $last_check !== null && $last_check > 0) {
            $difference = abs($url_count - $last_check);
            $percentage_change = ($difference / $last_check) * 100;

            if ($percentage_change >= $alert_threshold_percent) {
                sendAlert($user_id, $domain_url, $last_check, $url_count, $percentage_change);
                echo "Wysłano alert dla domeny: {$domain_url}\n";
            }
        }
    } else {
        echo "Błąd podczas sprawdzania domeny: {$domain_url}\n";
    }
} else {
    echo "Brak domen do sprawdzenia lub wszystkie domeny były sprawdzane w ciągu ostatnich {$default_interval} dni.\n";
}
// Funkcja pobierająca zawartość sitemapy
function fetchSitemapContent($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SitemapChecker/1.0)');
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    $content = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("Błąd cURL: " . curl_error($ch) . " dla URL: {$url}");
        curl_close($ch);
        return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200) {
        error_log("Błąd HTTP {$http_code} dla URL: {$url}");
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Sprawdź, czy zawartość jest nadal skompresowana (gzip)
    if (substr($content, 0, 2) === "\x1f\x8b") {
        $decompressed = @gzdecode($content);
        if ($decompressed !== false) {
            $content = $decompressed;
        } else {
            error_log("Nie udało się zdekompresować zawartości dla URL: {$url}");
            return false;
        }
    }

    return $content;
}

// Funkcja licząca URL-e z sitemapy
function countUrlsInSitemap($sitemap_url, $depth = 0) {
    $urls_count = 0;

    if ($depth > 10) {
        error_log("Osiągnięto maksymalną głębokość rekursji dla: {$sitemap_url}");
        return 0;
    }

    $sitemap_content = fetchSitemapContent($sitemap_url);
    if ($sitemap_content === false) {
        error_log("Nie udało się pobrać sitemapy z: {$sitemap_url}");
        return 0;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($sitemap_content);
    if ($xml === false) {
        error_log("Błąd parsowania XML dla: {$sitemap_url}");
        foreach(libxml_get_errors() as $error) {
            error_log($error->message);
        }
        libxml_clear_errors();
        return 0;
    }

    $namespaces = $xml->getDocNamespaces(true);
    foreach ($namespaces as $prefix => $namespace) {
        $xml->registerXPathNamespace($prefix ?: 'default', $namespace);
    }

    $sitemaps = $xml->xpath('//sitemap/loc | //default:sitemap/default:loc');
    if (!empty($sitemaps)) {
        foreach ($sitemaps as $sitemap) {
            $sub_sitemap_url = (string)$sitemap;
            $urls_count += countUrlsInSitemap($sub_sitemap_url, $depth + 1);
        }
    } else {
        $urls = $xml->xpath('//url/loc | //default:url/default:loc');
        $urls_count = count($urls);
    }

    return $urls_count;
}

// Funkcja zapisu sitemapy na serwerze
function saveSitemapToFile($domain_url, $domain_id, $user_id) {
    $uploads_dir = dirname(__FILE__) . '/sitemaps/' . $user_id;

    // Tworzenie katalogu, jeśli nie istnieje
    if (!file_exists($uploads_dir)) {
        if (!mkdir($uploads_dir, 0755, true)) {
            error_log("Nie udało się utworzyć katalogu: " . $uploads_dir);
            return false;
        }
    }

    $all_urls = array();
    getAllUrlsFromSitemap($domain_url, $all_urls);

    if (!empty($all_urls)) {
        // Tworzenie zawartości nowej sitemapy
        $sitemap_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap_content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($all_urls as $url) {
            $sitemap_content .= "  <url>\n    <loc>" . htmlspecialchars($url) . "</loc>\n  </url>\n";
        }
        $sitemap_content .= '</urlset>';

        // Tworzenie unikalnej nazwy pliku sitemapy
        $timestamp = time();
        $sitemap_filename = "{$domain_id}_{$user_id}_{$timestamp}.xml";
        $full_path = $uploads_dir . '/' . $sitemap_filename;

        // Zapisanie sitemapy na serwerze
        if (file_put_contents($full_path, $sitemap_content) === false) {
            error_log("Nie udało się zapisać sitemapy. Błąd: " . error_get_last()['message']);
            return false;
        } else {
            error_log("Sitemap została pomyślnie zapisana");

            // Usunięcie najstarszego pliku, jeśli są więcej niż 2 sitemapy
            $sitemaps = glob($uploads_dir . '/' . $domain_id . '_*.xml');
            usort($sitemaps, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            if (count($sitemaps) > 2) {
                unlink(end($sitemaps));
            }

            return true;
        }
    } else {
        error_log("Nie udało się pobrać żadnych URL-i z sitemapy.");
        return false;
    }
}

// Funkcja pobierająca URL-e z sitemapy
function getAllUrlsFromSitemap($sitemap_url, &$all_urls, $depth = 0) {
    if ($depth > 10) {
        error_log("Osiągnięto maksymalną głębokość rekursji dla: {$sitemap_url}");
        return;
    }

    $sitemap_content = fetchSitemapContent($sitemap_url);
    if ($sitemap_content === false) {
        error_log("Nie udało się pobrać sitemapy z: {$sitemap_url}");
        return;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($sitemap_content);
    if ($xml === false) {
        error_log("Błąd parsowania XML dla URL: {$sitemap_url}");
        foreach(libxml_get_errors() as $error) {
            error_log($error->message);
        }
        libxml_clear_errors();
        return;
    }

    $namespaces = $xml->getDocNamespaces(true);
    foreach ($namespaces as $prefix => $namespace) {
        $xml->registerXPathNamespace($prefix ?: 'default', $namespace);
    }

    $sitemaps = $xml->xpath('//sitemap/loc | //default:sitemap/default:loc');
    if (!empty($sitemaps)) {
        foreach ($sitemaps as $sitemap) {
            $sub_sitemap_url = (string)$sitemap;
            getAllUrlsFromSitemap($sub_sitemap_url, $all_urls, $depth + 1);
        }
    } else {
        $urls = $xml->xpath('//url/loc | //default:url/default:loc');
        foreach ($urls as $url) {
            $all_urls[] = (string)$url;
        }
    }
}

// Funkcja wysyłająca alerty e-mail do użytkowników
function sendAlert($user_id, $domain_url, $last_check, $current_check, $percentage_change) {
    global $conn;

    // Pobieramy dane użytkownika
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $email = $user['email'];
    $username = $user['username'];

    // Tworzymy treść e-maila
    $subject = "Zmiana liczby URL-i w sitemapie dla domeny {$domain_url}";
    $message = "
    {$username}, wykryliśmy zmianę liczby URL-i w sitemapie dla Twojej domeny {$domain_url}.
    
    Poprzednia liczba URL-i: {$last_check}
    Obecna liczba URL-i: {$current_check}
    Procentowa zmiana: {$percentage_change}%.

    
    ";

    // Wysyłamy e-mail (konfiguracja mailera będzie wymagana)
    mail($email, $subject, $message);
}
?>
