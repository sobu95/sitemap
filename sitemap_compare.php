<?php
// sitemap_compare.php — Strona do porównywania dwóch sitemap z różnych dat
require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

// Pobieranie ID domeny z parametru URL
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;
$user_id = $_SESSION['user_id']; // ID zalogowanego użytkownika

// Sprawdzenie, czy domena należy do zalogowanego użytkownika
$stmt = $conn->prepare("SELECT domain FROM domains WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $domain_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo 'Nie masz dostępu do tej domeny.';
    exit();
}

$domain = $result->fetch_assoc()['domain'];
$error = '';
$success = '';
$comparison_message = '';
$added_urls = [];
$removed_urls = [];
$filtered_urls = [];

// Pobieranie listy dat dla porównania
$stmt = $conn->prepare("SELECT id, checked_at FROM domain_checks WHERE domain_id = ? ORDER BY checked_at DESC");
$stmt->bind_param('i', $domain_id);
$stmt->execute();
$dates_result = $stmt->get_result();
$dates = $dates_result->fetch_all(MYSQLI_ASSOC);

// Obsługa porównania sitemap z dwóch różnych dat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date_1'], $_POST['date_2'])) {
    $date_1 = intval($_POST['date_1']);
    $date_2 = intval($_POST['date_2']);
    $filter_text = isset($_POST['filter_text']) ? trim($_POST['filter_text']) : '';

    // Pobranie wyników dla dwóch wybranych dat
    $stmt = $conn->prepare("SELECT result FROM domain_checks WHERE id = ? AND domain_id = ?");
    $stmt->bind_param('ii', $date_1, $domain_id);
    $stmt->execute();
    $result_1 = $stmt->get_result()->fetch_assoc()['result'];

    $stmt = $conn->prepare("SELECT result FROM domain_checks WHERE id = ? AND domain_id = ?");
    $stmt->bind_param('ii', $date_2, $domain_id);
    $stmt->execute();
    $result_2 = $stmt->get_result()->fetch_assoc()['result'];

    // Parsowanie wyników (zakładamy, że są w formacie JSON z listą URL-i)
    $urls_1 = json_decode($result_1, true);
    $urls_2 = json_decode($result_2, true);

    if (is_array($urls_1) && is_array($urls_2)) {
        // Znajdowanie dodanych i usuniętych URL-i
        $added_urls = array_diff($urls_2, $urls_1);
        $removed_urls = array_diff($urls_1, $urls_2);

        // Filtrowanie URL-i na podstawie wpisanego tekstu
        if ($filter_text !== '') {
            $added_urls = array_filter($added_urls, function($url) use ($filter_text) {
                return strpos($url, $filter_text) !== false;
            });
            $removed_urls = array_filter($removed_urls, function($url) use ($filter_text) {
                return strpos($url, $filter_text) !== false;
            });
        }

        // Tworzenie komunikatu o zmianach
        $comparison_message = 'Porównanie sitemapy z dwóch dat:<br>';
        $comparison_message .= 'Dodano URL-i: ' . count($added_urls) . '<br>';
        $comparison_message .= 'Usunięto URL-i: ' . count($removed_urls) . '<br>';
    } else {
        $error = 'Błąd podczas przetwarzania danych z wybranych dat.';
    }
}

?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Porównanie sitemap - ' . htmlspecialchars($domain); include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Porównanie sitemap: <?= htmlspecialchars($domain) ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Formularz wyboru dat do porównania oraz filtracji URL-i -->
    <form method="POST" action="">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="date_1" class="form-label">Wybierz pierwszą datę</label>
                    <select name="date_1" id="date_1" class="form-control" required>
                        <?php foreach ($dates as $date): ?>
                            <option value="<?= $date['id'] ?>"><?= htmlspecialchars($date['checked_at']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="date_2" class="form-label">Wybierz drugą datę</label>
                    <select name="date_2" id="date_2" class="form-control" required>
                        <?php foreach ($dates as $date): ?>
                            <option value="<?= $date['id'] ?>"><?= htmlspecialchars($date['checked_at']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Pole do filtracji URL-i -->
        <div class="mb-3">
            <label for="filter_text" class="form-label">Filtruj URL-e (opcjonalnie)</label>
            <input type="text" name="filter_text" id="filter_text" class="form-control" placeholder="Wpisz tekst do filtracji">
        </div>

        <button type="submit" class="btn btn-primary w-100">Porównaj sitemapy</button>
    </form>

    <?php if ($comparison_message): ?>
        <div class="mt-5">
            <h3 class="text-center">Wyniki porównania</h3>
            <div class="alert alert-info text-center"><?= $comparison_message ?></div>

            <!-- Lista dodanych URL-i -->
            <?php if (count($added_urls) > 0): ?>
                <h4>Dodane URL-e:</h4>
                <ul class="list-group mb-4">
                    <?php foreach ($added_urls as $url): ?>
                        <li class="list-group-item"><?= htmlspecialchars($url) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-center">Brak nowych URL-i.</p>
            <?php endif; ?>

            <!-- Lista usuniętych URL-i -->
            <?php if (count($removed_urls) > 0): ?>
                <h4>Usunięte URL-e:</h4>
                <ul class="list-group mb-4">
                    <?php foreach ($removed_urls as $url): ?>
                        <li class="list-group-item"><?= htmlspecialchars($url) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-center">Brak usuniętych URL-i.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
