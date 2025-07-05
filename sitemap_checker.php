<?php
// sitemap_checker.php — Skrypt do obsługi i liczenia URL-i w różnych formatach sitemapy (XML, GZ, indeksy sitemap)

// Funkcja pobierająca i licząca URL-e z sitemapy, z obsługą zagnieżdżonych sitemap
function countUrlsInSitemap($sitemap_url, $depth = 0) {
    // Maksymalna głębokość rekursji dla zagnieżdżonych sitemap
    if ($depth > 10) {
        error_log("Osiągnięto maksymalną głębokość rekursji dla: {$sitemap_url}");
        return 0;
    }

    // Pobieranie zawartości sitemapy
    $sitemap_content = fetchSitemapContent($sitemap_url);
    if ($sitemap_content === false) {
        error_log("Nie udało się pobrać sitemapy z: {$sitemap_url}");
        return 0;
    }

    // Obsługa formatu XML
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

    // Rejestrowanie namespace'ów, aby obsłużyć różne struktury XML
    $namespaces = $xml->getDocNamespaces(true);
    foreach ($namespaces as $prefix => $namespace) {
        $xml->registerXPathNamespace($prefix ?: 'default', $namespace);
    }

    // Sprawdzanie, czy jest to indeks sitemapy (zawiera inne sitemapy)
    $sitemaps = $xml->xpath('//sitemap/loc | //default:sitemap/default:loc');
    if (!empty($sitemaps)) {
        $urls_count = 0;
        // Przechodzimy przez wszystkie sitemapy z indeksu
        foreach ($sitemaps as $sitemap) {
            $sub_sitemap_url = (string)$sitemap;
            $urls_count += countUrlsInSitemap($sub_sitemap_url, $depth + 1); // Rekursyjne liczenie URL-i
        }
        return $urls_count;
    } else {
        // Jeśli nie jest to indeks, liczymy URL-e
        $urls = $xml->xpath('//url/loc | //default:url/default:loc');
        return count($urls);
    }
}

// Funkcja pobierająca zawartość sitemapy, obsługująca różne formaty (gzip, plain XML)
function fetchSitemapContent($url) {
    $content = '';

    // Inicjalizacja cURL do pobierania sitemapy
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Maksymalna liczba przekierowań
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);  // Maksymalny czas oczekiwania na odpowiedź
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SitemapChecker/1.0)');
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate'); // Obsługa kompresji
    $content = curl_exec($ch);
    
    // Sprawdzanie, czy wystąpił błąd podczas pobierania
    if (curl_errno($ch)) {
        error_log("Błąd cURL: " . curl_error($ch) . " dla URL: {$url}");
        curl_close($ch);
        return false;
    }

    // Sprawdzanie kodu odpowiedzi HTTP
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200) {
        error_log("Błąd HTTP {$http_code} dla URL: {$url}");
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);

    // Sprawdzanie, czy zawartość jest skompresowana (gzip)
    if (substr($content, 0, 2) === "\x1f\x8b") {
        $decompressed = @gzdecode($content);
        if ($decompressed !== false) {
            $content = $decompressed;
        } else {
            error_log("Nie udało się zdekompresować zawartości dla URL: {$url}");
            return false;
        }
    }

    // Sprawdzenie, czy zawartość to prawidłowy XML
    if (!simplexml_load_string($content)) {
        error_log("Zawartość nie jest prawidłowym XML dla URL: {$url}");
        return false;
    }

    return $content;
}

?>
