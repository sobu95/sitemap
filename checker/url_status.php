<?php
session_start();

// Przygotowanie HTML z wynikami
$results_html = '';
if (isset($_SESSION['results'])) {
    foreach ($_SESSION['results'] as $res) {
        $results_html .= '<tr>';
        $results_html .= '<td>' . htmlspecialchars($res['url']) . '</td>';
        $results_html .= '<td>' . $res['status'] . '</td>';
        $results_html .= '<td>' . (!empty($res['redirect']) ? htmlspecialchars($res['redirect']) : 'Brak') . '</td>';
        $results_html .= '</tr>';
    }
}

$response = [
    'results_html' => $results_html,
    'remaining_urls' => $_SESSION['remaining_urls'] ?? 0,
    'status_code' => $res['status'] ?? 200
];

echo json_encode($response);
?>
