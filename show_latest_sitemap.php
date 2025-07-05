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

// Katalog na sitemapy dla użytkownika
$uploads_dir = 'sitemaps/' . $user_id;
$sitemap_files = glob($uploads_dir . '/' . $domain_id . '_*.xml');

if (empty($sitemap_files)) {
    die('Nie znaleziono zapisanych sitemap dla tej domeny.');
}

// Sortuj pliki sitemap według daty modyfikacji (najnowsze na początku)
usort($sitemap_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Pobierz ścieżkę do najnowszej sitemapy
$latest_sitemap_path = $sitemap_files [0];

// Ustaw odpowiednie nagłówki, aby przeglądarka wyświetliła plik XML
header('Content-Type: application/xml');
header('Content-Disposition: inline; filename="' . basename($latest_sitemap_path) . '"');
header('Content-Length: ' . filesize($latest_sitemap_path));

// Wypisz zawartość pliku sitemap
readfile($latest_sitemap_path);
exit();
?>