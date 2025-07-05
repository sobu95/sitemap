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

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text'])) {
    // Wyciąganie URL-i za pomocą wyrażenia regularnego
    $text = $_POST['text'];
    $urls = preg_match_all('/(https?:\/\/\S+)/', $text, $matches);
    
    if (!empty($matches[0])) {
        $results = $matches[0];
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Ankor-PukSoft - Wyciąganie adresów URL z treści'; include 'inc/head.php'; ?>
<body>

<div class="container">
    <?php include('inc/sidebar.php'); ?>

    <main class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Domeny</a>
            <span class="breadcrumb-separator">/</span>
            <span>Wyciąganie URL</span>
        </div>

        <header class="header">
            <h1>Wyciąganie adresów URL z treści</h1>
        </header>

        <div class="content-panel">
            <form action="" method="post">
                <div class="form-group">
                    <label for="text" class="form-label">Wprowadź treść</label>
                    <textarea id="text" name="text" rows="10" class="form-control" placeholder="Wklej tutaj tekst zawierający adresy URL..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Wyciągnij URL-e
                </button>
            </form>
        </div>

        <?php if (!empty($results)): ?>
        <div class="content-panel">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-link"></i>
                Znalezione URL-e (<?= count($results) ?>)
            </h3>
            <div style="background-color: var(--sidebar-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
                <?php foreach ($results as $url): ?>
                    <div style="margin-bottom: 0.5rem;">
                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="color: var(--primary-blue); text-decoration: none;">
                            <i class="fa-solid fa-external-link-alt" style="margin-right: 0.5rem;"></i>
                            <?= htmlspecialchars($url) ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Nie znaleziono żadnych adresów URL w podanej treści.
        </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>