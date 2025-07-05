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
<?php $page_title = 'Wyciąganie adresów URL z treści'; include 'inc/head.php'; ?>
<body>

    <?php
    include('inc/sidebar.php');
    ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="container">
        <h1 class="text-center my-4">Wyciąganie adresów URL z treści</h1>

        <form action="" method="post">
            <div class="mb-3">
                <label for="text" class="form-label">Wprowadź treść</label>
                <textarea id="text" name="text" rows="10" class="form-control"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Wyślij</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text'])) {
            // Wyciąganie URL-i za pomocą wyrażenia regularnego
            $text = $_POST['text'];
            $urls = preg_match_all('/(https?:\/\/\S+)/', $text, $matches);
            
            echo '<div class="mt-4">';
            if (!empty($matches[0])) {
                $urls = $matches[0];
                echo "<h2>Znaleziono URLe:</h2><ul>";
                foreach ($urls as $url) {
                    echo '<li><a href="' . htmlspecialchars($url) . '" target="_blank">' . htmlspecialchars($url) . '</a></li>';
                }
                echo "</ul>";
            } else {
                echo '<div class="alert alert-warning">Nie znaleziono żadnych adresów URL w podanej treści.</div>';
            }
            echo '</div>';
        }
        ?>

    </div>
</main>

</body>
</html>
