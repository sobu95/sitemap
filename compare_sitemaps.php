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

function simplifyDomain($url) {
    $parsed_url = parse_url($url);
    $domain = isset($parsed_url['host']) ? $parsed_url['host'] : $parsed_url['path'];
    return preg_replace('/^www\./', '', $domain);
}
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'SiteMap Checker - Porównanie sitemap'; include 'inc/head.php'; ?>
<body>

<div class="container">
    <?php include('inc/sidebar.php'); ?>
    
    <main class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Domeny</a>
            <span class="breadcrumb-separator">/</span>
            <a href="domain.php?id=<?= $domain_id ?>"><?= htmlspecialchars(simplifyDomain($domain['domain'])) ?></a>
            <span class="breadcrumb-separator">/</span>
            <span>Porównanie sitemap</span>
        </div>

        <header class="header">
            <h1><?= htmlspecialchars(simplifyDomain($domain['domain'])) ?></h1>
            <div class="header-actions">
                <a href="domain.php?id=<?= $domain_id ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                    Powrót
                </a>
            </div>
        </header>

        <!-- Formularz do filtrowania URL-i -->
        <div class="content-panel">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-filter"></i>
                Filtrowanie URL-i
            </h3>
            <form method="GET" action="">
                <input type="hidden" name="domain_id" value="<?= $domain_id ?>">
                <div style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 300px;">
                        <label for="filter_text" class="form-label">Filtruj URL-e:</label>
                        <input type="text" name="filter_text" id="filter_text" class="form-control" value="<?= htmlspecialchars($filter_text) ?>" placeholder="Wpisz tekst do filtrowania">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-filter"></i>
                            Filtruj
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Wyniki porównania -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Dodane URL -->
            <div class="content-panel">
                <h3 style="margin-bottom: 1.5rem; color: var(--green-accent);">
                    <i class="fa-solid fa-plus-circle"></i>
                    Dodane URL-e
                </h3>
                <p style="color: var(--text-dark); margin-bottom: 1rem;">
                    Znaleziono <?= $filtered_added_count ?> adresów URL dodanych.
                </p>
                <?php if (count($added_urls) > 0): ?>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($added_urls as $url): ?>
                            <div style="background-color: var(--sidebar-bg); padding: 0.8rem; margin-bottom: 0.5rem; border-radius: 6px; border-left: 3px solid var(--green-accent);">
                                <a href="<?= htmlspecialchars($url) ?>" rel="nofollow" target="_blank" style="color: var(--text-light); text-decoration: none; font-size: 0.9rem; word-break: break-all;">
                                    <i class="fa-solid fa-external-link-alt" style="margin-right: 0.5rem; color: var(--green-accent);"></i>
                                    <?= htmlspecialchars($url) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-dark); padding: 2rem;">
                        <i class="fa-solid fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i><br>
                        Brak nowych URL-i.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Usunięte URL -->
            <div class="content-panel">
                <h3 style="margin-bottom: 1.5rem; color: var(--red-accent);">
                    <i class="fa-solid fa-minus-circle"></i>
                    Usunięte URL-e
                </h3>
                <p style="color: var(--text-dark); margin-bottom: 1rem;">
                    Znaleziono <?= $filtered_removed_count ?> adresów URL usuniętych.
                </p>
                <?php if (count($removed_urls) > 0): ?>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($removed_urls as $url): ?>
                            <div style="background-color: var(--sidebar-bg); padding: 0.8rem; margin-bottom: 0.5rem; border-radius: 6px; border-left: 3px solid var(--red-accent);">
                                <a href="<?= htmlspecialchars($url) ?>" rel="nofollow" target="_blank" style="color: var(--text-light); text-decoration: none; font-size: 0.9rem; word-break: break-all;">
                                    <i class="fa-solid fa-external-link-alt" style="margin-right: 0.5rem; color: var(--red-accent);"></i>
                                    <?= htmlspecialchars($url) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-dark); padding: 2rem;">
                        <i class="fa-solid fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i><br>
                        Brak usuniętych URL-i.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Paginacja -->
        <?php if ($total_pages > 1): ?>
        <div style="text-align: center; margin-top: 2rem;">
            <nav>
                <ul class="pagination">
                    <?= generatePagination($page, $total_pages, $domain_id, $filter_text); ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>