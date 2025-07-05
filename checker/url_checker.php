<?php
session_start();

// Funkcja do generowania losowych User-Agentów
function get_random_user_agent() {
    $user_agents = [
        'Googlebot/2.1 (+http://www.google.com/bot.html)',
        'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        'Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)',
        'DuckDuckBot/1.0; (+http://duckduckgo.com/duckduckbot.html)',
        'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.96 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
    ];
    return $user_agents[array_rand($user_agents)];
}

// Funkcja do sprawdzania statusu i przekierowania
function get_http_status_and_redirect($url, $rate_limit) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    // Ustaw losowy User-Agent, jeśli jest rate-limiting
    if ($rate_limit == 'yes') {
        curl_setopt($ch, CURLOPT_USERAGENT, get_random_user_agent());
    }

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect_url = '';

    if ($http_code >= 300 && $http_code < 400) {
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$redirect_url) {
            if (preg_match('/^Location:\s*(.*)$/mi', $header, $matches)) {
                $redirect_url = trim($matches[1]);
            }
            return strlen($header);
        });
        curl_exec($ch);
    }

    curl_close($ch);
    return ['status' => $http_code, 'redirect' => $redirect_url];
}

// Pobieranie adresów URL i rate-limit z formularza
$urls = isset($_POST['urls']) ? explode(PHP_EOL, trim($_POST['urls'])) : [];
$rate_limit = isset($_POST['rate_limit']) ? $_POST['rate_limit'] : 'no';

// Inicjalizacja wyników
$results = [];
$_SESSION['remaining_urls'] = count($urls);
$_SESSION['results'] = []; // Resetowanie wyników

// Przetwarzanie URL-i
foreach ($urls as $url) {
    $url = trim($url);
    if (!empty($url)) {
        $result = get_http_status_and_redirect($url, $rate_limit);
        $_SESSION['results'][] = [
            'url' => $url,
            'status' => $result['status'],
            'redirect' => $result['redirect']
        ];
        $_SESSION['remaining_urls']--;

        // Dodanie opóźnienia, jeśli wybrano rate-limiting
        if ($rate_limit == 'yes') {
            sleep(rand(1, 2)); // Losowe opóźnienie 1-2 sekundy
        }

        // Sprawdzenie kodu 429
        if ($result['status'] == 429) {
            break;
        }
    }
}

// Przygotowanie HTML z wynikami
$results_html = '';
foreach ($_SESSION['results'] as $res) {
    $results_html .= '<tr>';
    $results_html .= '<td>' . htmlspecialchars($res['url']) . '</td>';
    $results_html .= '<td>' . $res['status'] . '</td>';
    $results_html .= '<td>' . (!empty($res['redirect']) ? htmlspecialchars($res['redirect']) : 'Brak') . '</td>';
    $results_html .= '</tr>';
}

// Przekazanie wyników do klienta
$response = [
    'results_html' => $results_html,
    'remaining_urls' => $_SESSION['remaining_urls'],
    'status_code' => $result['status'] ?? 200
];

echo json_encode($response);
?>
