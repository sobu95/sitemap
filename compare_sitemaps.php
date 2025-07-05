<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

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
$success_message = '';
$error_message = '';

// Katalog na sitemapy dla użytkownika
$uploads_dir = 'sitemaps/' . $user_id;
$sitemaps = glob($uploads_dir . '/' . $domain_id . '_*.xml');
usort($sitemaps, function($a, $b) {
    return filemtime($b) - filemtime($a); // Sortowanie od najnowszych
});

// Sprawdzenie, czy są co najmniej 2 sitemapy
if (count($sitemaps) < 2) {
    die('Brak wystarczającej liczby zapisanych sitemap do porównania.');
}

// Funkcja wczytująca URL-e z sitemapy
function loadUrlsFromSitemap($sitemap_file) {
    $xml = simplexml_load_file($sitemap_file);
    $urls = [];

    foreach ($xml->url as $url) {
        $urls[] = (string) $url->loc;
    }

    return $urls;
}

// Pobieramy dwie najnowsze sitemapy
$sitemap_new = loadUrlsFromSitemap($sitemaps[0]); // najnowsza
$sitemap_old = loadUrlsFromSitemap($sitemaps[1]); // poprzednia

// Porównanie sitemap - dodane i usunięte URL-e
$added_urls = array_diff($sitemap_new, $sitemap_old);
$removed_urls = array_diff($sitemap_old, $sitemap_new);

// Pobieranie tekstu do filtrowania (jeśli istnieje)
$filter_text = isset($_GET['filter_text']) ? trim($_GET['filter_text']) : '';

// Filtrowanie URL-i, jeśli tekst do filtrowania jest ustawiony
$filtered_added_count = count($added_urls);
$filtered_removed_count = count($removed_urls);

if ($filter_text !== '') {
    $added_urls = array_filter($added_urls, function($url) use ($filter_text) {
        return strpos($url, $filter_text) !== false;
    });
    $removed_urls = array_filter($removed_urls, function($url) use ($filter_text) {
        return strpos($url, $filter_text) !== false;
    });

    // Zaktualizuj licznik URL-i po filtracji
    $filtered_added_count = count($added_urls);
    $filtered_removed_count = count($removed_urls);
}

// Paginacja
$urls_per_page = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Obliczenie liczby stron
$total_added_pages = ceil(count($added_urls) / $urls_per_page);
$total_removed_pages = ceil(count($removed_urls) / $urls_per_page);
$total_pages = max($total_added_pages, $total_removed_pages);

// Paginacja dodanych URL
$added_urls = array_slice($added_urls, ($page - 1) * $urls_per_page, $urls_per_page);

// Paginacja usuniętych URL
$removed_urls = array_slice($removed_urls, ($page - 1) * $urls_per_page, $urls_per_page);

// Funkcja do generowania elementów paginacji
function generatePagination($current_page, $total_pages, $domain_id, $filter_text) {
    $pagination_html = '';
    
    // Zawsze dodajemy pierwszą stronę
    if ($current_page > 1) {
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?domain_id=' . $domain_id . '&page=1&filter_text=' . urlencode($filter_text) . '">1</a></li>';
        if ($current_page > 6) {
            $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Wyświetlanie czterech stron wokół bieżącej strony
    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $active_class = ($i == $current_page) ? ' active' : '';
        $pagination_html .= '<li class="page-item' . $active_class . '"><a class="page-link" href="?domain_id=' . $domain_id . '&page=' . $i . '&filter_text=' . urlencode($filter_text) . '">' . $i . '</a></li>';
    }

    // Dodajemy wielokropek, jeśli nie wyświetliliśmy ostatnich stron
    if ($current_page < $total_pages - 4) {
        $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    // Zawsze dodajemy ostatnią stronę
    if ($current_page < $total_pages) {
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?domain_id=' . $domain_id . '&page=' . $total_pages . '&filter_text=' . urlencode($filter_text) . '">' . $total_pages . '</a></li>';
    }

    return $pagination_html;
}
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Porównanie sitemap - ' . htmlspecialchars($domain['domain']); include 'inc/head.php'; ?>
<body>
    <?php include('inc/sidebar.php'); ?>
    
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="container mt-5">
            <h1 class="text-center mb-4">Porównanie sitemap: <?= htmlspecialchars($domain['domain']) ?></h1>

            <!-- Formularz do filtrowania URL-i -->
            <form method="GET" action="" class="mb-4">
                <input type="hidden" name="domain_id" value="<?= $domain_id ?>">
                <div class="mb-3">
                    <label for="filter_text" class="form-label">Filtruj URL-e:</label>
                    <input type="text" name="filter_text" id="filter_text" class="form-control" value="<?= htmlspecialchars($filter_text) ?>" placeholder="Wpisz tekst do filtrowania">
                </div>
                <button type="submit" class="btn btn-primary">Filtruj</button>
            </form>

            <div class="row">
                <!-- Dodane URL -->
                <div class="col-md-6">
                    <h3>Dodane URL:</h3>
                    <?php if (count($added_urls) > 0): ?>
                      <p>Znaleziono <?= $filtered_added_count ?> adresów URL dodanych.</p>
                        <ul class="list-group mb-4">
                            <?php foreach ($added_urls as $url): ?>
                                <li class="list-group-item" style="font-size: 13px;"><a href="<?= htmlspecialchars($url) ?>" rel="nofollow" target="_blank"><?= htmlspecialchars($url) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-success">Brak nowych URL-i.</div>
                    <?php endif; ?>
                </div>

                <!-- Usunięte URL -->
                <div class="col-md-6">
                    <h3>Usunięte URL:</h3>
                    <?php if (count($removed_urls) > 0): ?>
                       <p>Znaleziono <?= $filtered_removed_count ?> adresów URL usuniętych.</p>
                        <ul class="list-group mb-4">
                            <?php foreach ($removed_urls as $url): ?>
                                <li class="list-group-item" style="font-size: 13px;"><a href="<?= htmlspecialchars($url) ?>" rel="nofollow" target="_blank"><?= htmlspecialchars($url) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-danger">Brak usuniętych URL-i.</div>
                    <?php endif; ?>
                   
                </div>
            </div>

            <!-- Paginacja -->
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?= generatePagination($page, $total_pages, $domain_id, $filter_text); ?>
                </ul>
            </nav>

            <!-- Powrót do widoku szczegółów domeny -->
            <div class="text-center mt-3 mb-5">
                <a href="domain.php?id=<?= $domain_id ?>" class="btn btn-secondary">Powrót do szczegółów domeny</a>
            </div>
        </div>
    </main>
</body>
</html>
