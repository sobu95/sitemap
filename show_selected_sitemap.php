<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

// Pobieramy ID domeny z parametru URL
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;
$user_id = $_SESSION['user_id'];
$selected_sitemap_filename = isset($_GET['sitemap_file']) ? trim($_GET['sitemap_file']) : '';

// Sprawdzamy, czy domena należy do zalogowanego użytkownika
$stmt = $conn->prepare("SELECT domain FROM domains WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $domain_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Nie znaleziono domeny lub brak uprawnień.');
}

$domain = $result->fetch_assoc();
$domain_url = $domain['domain'];

if (empty($selected_sitemap_filename)) {
    die('Nie wybrano pliku sitemapy.');
}

// Katalog na sitemapy dla użytkownika
$uploads_dir = 'sitemaps/' . $user_id;
$sitemap_path = $uploads_dir . '/' . $selected_sitemap_filename;

// Sprawdź, czy plik istnieje i czy pasuje do wzorca (bezpieczeństwo)
if (!file_exists($sitemap_path) || strpos($selected_sitemap_filename, $domain_id . '_') !== 0 || substr($selected_sitemap_filename, -4) !== '.xml') {
    die('Wybrany plik sitemapy jest nieprawidłowy lub nie istnieje.');
}

// Ustaw odpowiednie nagłówki, aby przeglądarka wyświetliła plik XML
header('Content-Type: application/xml');
header('Content-Disposition: inline; filename="' . basename($sitemap_path) . '"');
header('Content-Length: ' . filesize($sitemap_path));

// Wypisz zawartość pliku sitemap
readfile($sitemap_path);
exit();
?>