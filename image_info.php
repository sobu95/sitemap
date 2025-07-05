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

// Funkcja do pobrania grafik z elementów zawierających "blog" w klasie lub identyfikatorze i zwrócenia ich wymiarów
function get_images_from_blog_section($url) {
    // Pobranie zawartości strony
    $html = @file_get_contents($url);
    if (!$html) {
        return null; // Zwraca null, jeśli nie można pobrać zawartości
    }

    // Załadowanie HTML do DOMDocument
    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    // Szukanie elementów zawierających "blog" w klasie lub identyfikatorze
    $xpath = new DOMXPath($dom);
    $blog_elements = $xpath->query('//*[contains(@class, "blog") or contains(@id, "blog")]');

    if ($blog_elements->length == 0) {
        return null; // Zwraca null, jeśli nie znaleziono elementów zawierających "blog"
    }

    $image_data = [];
    $processed_images = []; // Tablica do przechowywania unikalnych URLi przetworzonych grafik

    // Iteracja po znalezionych elementach
    foreach ($blog_elements as $element) {
        $images = $element->getElementsByTagName('img');

        foreach ($images as $img) {
            // Pobranie URL grafiki
            $img_src = $img->getAttribute('src');

            // Pomiń obrazki, które zawierają "logo" w nazwie
            if (stripos($img_src, 'logo') !== false) {
                continue;
            }

            // Pełen URL grafiki
            if (strpos($img_src, 'http') === false) {
                $img_src = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/' . ltrim($img_src, '/');
            }

            // Sprawdzenie, czy grafika już została przetworzona
            if (in_array($img_src, $processed_images)) {
                continue; // Pomiń tę grafikę, jeśli została już dodana do wyników
            }

            // Dodaj grafikę do przetworzonych
            $processed_images[] = $img_src;

            // Pobranie rzeczywistych wymiarów grafiki
            $real_dimensions = @getimagesize($img_src);
            if (!$real_dimensions) {
                continue; // Jeśli nie uda się pobrać rzeczywistych wymiarów, przechodzi do następnego obrazka
            }

            $real_width = $real_dimensions[0];
            $real_height = $real_dimensions[1];

            // Pomiń obrazki mniejsze niż 100x100px
            if ($real_width < 100 || $real_height < 100) {
                continue;
            }

            // Pobranie wymiarów wyświetlanej grafiki
            $display_width = $img->getAttribute('width');
            $display_height = $img->getAttribute('height');

            if (!$display_width || !$display_height) {
                // Jeśli atrybuty width/height nie są ustawione, sprawdź style CSS
                $style = $img->getAttribute('style');
                if (preg_match('/width:\s*(\d+)px/', $style, $matches)) {
                    $display_width = $matches[1];
                }
                if (preg_match('/height:\s*(\d+)px/', $style, $matches)) {
                    $display_height = $matches[1];
                }

                // Jeśli nadal brak danych, spróbuj uzyskać max-width z CSS
                if (!$display_width) {
                    $class = $img->getAttribute('class');
                    $display_width = extract_max_width_from_css($class) ?: ($real_width * 0.33); // Przyjmujemy domyślnie 33% dla desktopa
                }

                // Oblicz wysokość proporcjonalnie do rzeczywistych wymiarów
                if ($display_width && !$display_height) {
                    $display_height = ($display_width / $real_width) * $real_height;
                }
            }

            // Dodanie danych grafiki do tablicy wynikowej
            $image_data[] = [
                'src' => $img_src,
                'display_width' => $display_width,
                'display_height' => $display_height,
                'real_width' => $real_width,
                'real_height' => $real_height
            ];
        }
    }

    return $image_data; // Zwraca tablicę ze wszystkimi unikalnymi grafikami znalezionymi w sekcjach "blog"
}

// Funkcja do wyciągnięcia max-width z CSS (symulacja, należy ją dopracować, aby czytać plik CSS lub <style> w nagłówku strony)
function extract_max_width_from_css($class) {
    // Symulacja obliczeń na podstawie informacji o ekranie
    // Zakładamy, że szerokość ekranu wynosi 1920px
    $screen_width = 1920;

    // Na podstawie założenia: max-width: 33%;
    $max_width_percentage = 0.33;

    return $screen_width * $max_width_percentage;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['text'])) {
    // Wyciąganie URL-i za pomocą wyrażenia regularnego
    $text = $_POST['text'];
    $urls = preg_match_all('/(https?:\/\/\S+)/', $text, $matches);

    if (!empty($matches[0])) {
        $urls = $matches[0];
        foreach ($urls as $url) {
            $image_data = get_images_from_blog_section($url);
            $results[] = [
                'url' => $url,
                'images' => $image_data
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Ankor-PukSoft - Wyciąganie wymiarów grafik z URL'; include 'inc/head.php'; ?>
<body>

<div class="container">
    <?php include('inc/sidebar.php'); ?>

    <main class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Domeny</a>
            <span class="breadcrumb-separator">/</span>
            <span>Info o grafikach</span>
        </div>

        <header class="header">
            <h1>Wyciąganie wymiarów grafik z URL</h1>
        </header>

        <div class="content-panel">
            <form action="" method="post">
                <div class="form-group">
                    <label for="text" class="form-label">Wprowadź listę URL</label>
                    <textarea id="text" name="text" rows="10" class="form-control" placeholder="Wklej tutaj adresy URL stron do analizy..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-search"></i>
                    Analizuj grafiki
                </button>
            </form>
        </div>

        <?php if (!empty($results)): ?>
            <?php foreach ($results as $result): ?>
                <div class="content-panel">
                    <h3 style="margin-bottom: 1.5rem;">
                        <i class="fa-solid fa-globe"></i>
                        <a href="<?= htmlspecialchars($result['url']) ?>" target="_blank" style="color: var(--primary-blue); text-decoration: none;">
                            <?= htmlspecialchars($result['url']) ?>
                        </a>
                    </h3>

                    <?php if ($result['images']): ?>
                        <div style="display: grid; gap: 1rem;">
                            <?php foreach ($result['images'] as $image_info): ?>
                                <div style="background-color: var(--sidebar-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color);">
                                    <div style="margin-bottom: 0.5rem;">
                                        <strong>Grafika:</strong> 
                                        <a href="<?= htmlspecialchars($image_info['src']) ?>" target="_blank" style="color: var(--primary-blue); text-decoration: none;">
                                            <?= htmlspecialchars($image_info['src']) ?>
                                        </a>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; color: var(--text-dark);">
                                        <div>
                                            <strong>Wymiary wyświetlane:</strong><br>
                                            <?= htmlspecialchars($image_info['display_width'] ?: 'Nieznane') ?>x<?= htmlspecialchars($image_info['display_height'] ?: 'Nieznane') ?>px
                                        </div>
                                        <div>
                                            <strong>Rzeczywiste wymiary:</strong><br>
                                            <?= htmlspecialchars($image_info['real_width']) ?>x<?= htmlspecialchars($image_info['real_height']) ?>px
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            Nie znaleziono grafik w sekcji "blog" lub nie udało się pobrać ich wymiarów.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
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