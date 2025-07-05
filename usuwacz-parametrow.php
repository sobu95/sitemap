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

$filtered_urls = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pobieramy listę URLi z formularza
    $urls = isset($_POST['urls']) ? explode(PHP_EOL, trim($_POST['urls'])) : [];
    // Pobieramy frazy, które mają być sprawdzone w URLach
    $exclude_phrases = isset($_POST['exclude_phrases']) ? explode(',', trim($_POST['exclude_phrases'])) : [];

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
}
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Ankor-PukSoft - Usuwanie adresów URL z parametrem'; include 'inc/head.php'; ?>
<body>

<div class="container">
    <?php include('inc/sidebar.php'); ?>

    <main class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Domeny</a>
            <span class="breadcrumb-separator">/</span>
            <span>Filtr URL</span>
        </div>

        <header class="header">
            <h1>Filtrowanie adresów URL</h1>
        </header>

        <div class="content-panel">
            <!-- Formularz HTML do wprowadzenia danych -->
            <form method="post">
                <div class="form-group">
                    <label for="urls" class="form-label">Wprowadź adresy URL (jeden na linię):</label>
                    <textarea class="form-control" id="urls" name="urls" rows="10" placeholder="https://example.com/page1&#10;https://example.com/page2?param=value&#10;..."></textarea>
                </div>
                <div class="form-group">
                    <label for="exclude_phrases" class="form-label">Wprowadź frazy do wykluczenia (oddzielone przecinkami):</label>
                    <input type="text" class="form-control" id="exclude_phrases" name="exclude_phrases" placeholder="np. ?, .webp, /admin">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-filter"></i>
                    Filtruj URL-e
                </button>
            </form>
        </div>

        <?php if (!empty($filtered_urls)): ?>
        <div class="content-panel">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-list"></i>
                Przefiltrowane URL-e (<?= count($filtered_urls) ?>)
            </h3>
            <div style="background-color: var(--sidebar-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;">
<?= htmlspecialchars(implode(PHP_EOL, $filtered_urls)) ?>
            </div>
        </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Wszystkie URL-e zostały odfiltrowane lub nie wprowadzono żadnych URL-i.
        </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>