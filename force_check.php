<?php
require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest zalogowany jako admin
if (!is_logged_in() || !is_admin()) {
    header('Location: index.php');
    exit();
}

// Pobranie ID domeny z parametru GET
$domain_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($domain_id > 0) {
    // Pobranie szczegółów domeny
    $stmt = $conn->prepare("SELECT domain FROM domains WHERE id = ?");
    $stmt->bind_param('i', $domain_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $domain = $result->fetch_assoc();
        $domain_url = $domain['domain'];

        // Katalog na sitemapy dla użytkownika
        $uploads_dir = dirname(__FILE__) . '/sitemaps/' . $_SESSION['user_id'];
        if (!file_exists($uploads_dir)) {
            if (!mkdir($uploads_dir, 0755, true)) {
                error_log("Nie udało się utworzyć katalogu: " . $uploads_dir);
                die('Nie udało się utworzyć katalogu na sitemapy.');
            }
        }

        error_log("Rozpoczęto sprawdzanie sitemapy dla domeny: {$domain_url}");
        
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

            // Zapisanie zawartości sitemapy na serwerze
            $sitemap_filename = createSitemapFilename($domain_id, $_SESSION['user_id']);
            $full_path = $uploads_dir . '/' . $sitemap_filename;
            error_log("Próba zapisu sitemapy do: " . $full_path);
            
            if (file_put_contents($full_path, $sitemap_content) === false) {
                error_log("Nie udało się zapisać sitemapy. Błąd: " . error_get_last()['message']);
                $error_message = 'Nie udało się zapisać sitemapy na serwerze.';
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

                // Zapisanie wyniku do bazy danych
                $url_count = count($all_urls);
                $stmt = $conn->prepare("INSERT INTO domain_checks (domain_id, result) VALUES (?, ?)");
                $stmt->bind_param('ii', $domain_id, $url_count);
                if ($stmt->execute()) {
                    // Logowanie aktywności administratora
                    log_activity($_SESSION['user_id'], "Wymusił sprawdzenie sitemapy dla domeny: $domain_url");
                    
                    // Przekierowanie z komunikatem sukcesu
                    header("Location: admin.php?success=Sprawdzono domenę $domain_url. Znaleziono $url_count URL-i.");
                    exit();
                } else {
                    $error_message = 'Wystąpił błąd podczas zapisu wyników do bazy danych.';
                    error_log($error_message);
                }
            }
        } else {
            $error_message = 'Nie udało się pobrać żadnych URL-i z sitemapy.';
            error_log($error_message);
        }
    } else {
        $error_message = 'Nie znaleziono domeny o podanym ID.';
    }
} else {
    $error_message = 'Nie podano poprawnego ID domeny.';
}

// Wyświetlenie komunikatu o błędzie
if (isset($error_message)) {
    echo "<div class='alert alert-danger'>$error_message</div>";
}
// Tworzenie unikalnej nazwy pliku sitemapy
function createSitemapFilename($domain_id, $user_id) {
    $timestamp = time();
    return "{$domain_id}_{$user_id}_{$timestamp}.xml";
}

// Pobieranie zawartości sitemapy
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
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($http_code != 200) {
        error_log("Błąd HTTP {$http_code} dla URL: {$url}");
        return false;
    }

    // Rozszerzona obsługa typów MIME
    $valid_mime_types = ['application/xml', 'text/xml', 'application/x-gzip', 'text/plain'];
    $is_valid_mime = false;
    foreach ($valid_mime_types as $mime) {
        if (strpos($content_type, $mime) !== false) {
            $is_valid_mime = true;
            break;
        }
    }

    if (!$is_valid_mime) {
        error_log("Niepoprawny typ zawartości: {$content_type}. Oczekiwano XML lub gzip dla URL: {$url}");
        return false;
    }

    // Obsługa skompresowanych plików
    if (strpos($content_type, 'application/x-gzip') !== false || substr($content, 0, 2) === "\x1f\x8b") {
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

// Funkcja rekurencyjnie pobierająca wszystkie URL-e z sitemap
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

// Sprawdzanie sitemapy
if (isset($_POST['check_now'])) {
    error_log("Rozpoczęto sprawdzanie sitemapy dla domeny: {$domain_url}");
    
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

        // Zapisanie zawartości sitemapy na serwerze
        $sitemap_filename = createSitemapFilename($domain_id, $user_id);
        $full_path = $uploads_dir . '/' . $sitemap_filename;
        error_log("Próba zapisu sitemapy do: " . $full_path);
        
        if (file_put_contents($full_path, $sitemap_content) === false) {
            error_log("Nie udało się zapisać sitemapy. Błąd: " . error_get_last()['message']);
            $error_message = 'Nie udało się zapisać sitemapy na serwerze.';
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

            // Zapisanie wyniku do bazy danych
            $url_count = count($all_urls);
            $stmt = $conn->prepare("INSERT INTO domain_checks (domain_id, result) VALUES (?, ?)");
            $stmt->bind_param('ii', $domain_id, $url_count);
            if ($stmt->execute()) {
                $success_message = 'Sitemap została pomyślnie sprawdzona. Znaleziono ' . $url_count . ' URL-i.';
                error_log($success_message);
            } else {
                $error_message = 'Wystąpił błąd podczas zapisu wyników do bazy danych.';
                error_log($error_message);
            }
        }
    } else {
        $error_message = 'Nie udało się pobrać żadnych URL-i z sitemapy.';
        error_log($error_message);
    }
}