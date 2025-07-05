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
<?php $page_title = 'Status checker'; include 'inc/head.php'; ?>
<body>

    <?php include('inc/sidebar.php'); ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="container">

<?php
// Funkcja do sprawdzania statusu i przekierowania
function get_http_status_and_redirect($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Nie podążaj automatycznie za przekierowaniem
    curl_setopt($ch, CURLOPT_NOBODY, true); // Pobieraj tylko nagłówki, nie zawartość
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_exec($ch);

    // Pobierz status HTTP
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect_url = '';

    // Sprawdź, czy jest to przekierowanie (3xx)
    if ($http_code >= 300 && $http_code < 400) {
        // Pobierz wszystkie nagłówki
        $headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
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

// Sprawdzamy, czy formularz został przesłany
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pobieramy listę URLi z formularza
    $urls = isset($_POST['urls']) ? explode(PHP_EOL, trim($_POST['urls'])) : [];

    // Lista przechowująca wyniki
    $results = [];

    // Iteracja po URL-ach
    foreach ($urls as $url) {
        $url = trim($url);
        if (!empty($url)) {
            $result = get_http_status_and_redirect($url);
            $results[] = [
                'url' => $url,
                'status' => $result['status'],
                'redirect' => $result['redirect']
            ];
        }
    }

    // Wyświetlamy wyniki
    echo '<h3>Wynik:</h3>';
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>URL</th><th>Status</th><th>Przekierowanie</th></tr></thead>';
    echo '<tbody>';
    foreach ($results as $res) {
        // Dodajemy klasę "table-success" do wierszy o statusie 200
        $row_class = ($res['status'] == 200) ? 'table-success' : '';
        echo '<tr class="' . $row_class . '">';
        echo '<td>' . htmlspecialchars($res['url']) . '</td>';
        echo '<td>' . $res['status'] . '</td>';
        echo '<td>' . (!empty($res['redirect']) ? htmlspecialchars($res['redirect']) : 'Brak') . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}
?>

<!-- Formularz HTML do wprowadzenia danych -->
<form method="post">
    <div class="mb-3">
        <label for="urls" class="form-label">Wprowadź adresy URL (jeden na linię):</label>
        <textarea class="form-control" id="urls" name="urls" rows="10" placeholder="Wprowadź adresy URL"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Sprawdź URL-e</button>
</form>

    </div>
</main>

</body>
</html>
