<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// dashboard.php — Panel użytkownika z logowaniem aktywności, zarządzaniem domenami i sitemapami
require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Pobieramy ID zalogowanego użytkownika
$username = $_SESSION['username']; // Pobieramy nazwę użytkownika
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Usuwanie adresów URL z parametrem'; include 'inc/head.php'; ?>
<body>

    <?php include('inc/sidebar.php'); ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="container">
 
<?php
// Sprawdzamy, czy formularz został przesłany
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pobieramy listę URLi z formularza
    $urls = isset($_POST['urls']) ? explode(PHP_EOL, trim($_POST['urls'])) : [];
    // Pobieramy frazy, które mają być sprawdzone w URLach
    $exclude_phrases = isset($_POST['exclude_phrases']) ? explode(',', trim($_POST['exclude_phrases'])) : [];

    // Lista przechowująca przefiltrowane URL-e
    $filtered_urls = [];

    // Iteracja po URL-ach
    foreach ($urls as $url) {
        $url = trim($url);
        $exclude = false;

        // Sprawdzamy, czy URL zawiera którąkolwiek z fraz do wykluczenia
        foreach ($exclude_phrases as $phrase) {
            if (strpos($url, trim($phrase)) !== false) {
                $exclude = true;
                break;
            }
        }

        // Jeżeli URL nie zawiera żadnej z fraz, dodajemy go do listy
        if (!$exclude) {
            $filtered_urls[] = $url;
        }
    }

    // Wyświetlamy wynik
    echo '<h3>Wynik:</h3>';
    echo '<pre>' . implode(PHP_EOL, $filtered_urls) . '</pre>';
}
?>

<!-- Formularz HTML do wprowadzenia danych -->
<form method="post">
    <div class="mb-3">
        <label for="urls" class="form-label">Wprowadź adresy URL (jeden na linię):</label>
        <textarea class="form-control" id="urls" name="urls" rows="10" placeholder="Wprowadź adresy URL"></textarea>
    </div>
    <div class="mb-3">
        <label for="exclude_phrases" class="form-label">Wprowadź frazy do wykluczenia (oddzielone przecinkami):</label>
        <input type="text" class="form-control" id="exclude_phrases" name="exclude_phrases" placeholder="np. ?, .webp">
    </div>
    <button type="submit" class="btn btn-primary">Filtruj URL-e</button>
</form>


    </div>
</main>

</body>
</html>