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
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Wyciąganie wymiarów grafik z URL'; include 'inc/head.php'; ?>
<body>

    <?php
    include('inc/sidebar.php');
    ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="container">
        <h1 class="text-center my-4">Wyciąganie wymiarów grafik z URL</h1>

        <form action="" method="post">
            <div class="mb-3">
                <label for="text" class="form-label">Wprowadź listę URL</label>
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
                echo "<h2>Znaleziono URLe:</h2><ul class='list-group'>";
                foreach ($urls as $url) {
                    $image_data = get_images_from_blog_section($url);
                    echo '<li class="list-group-item">';
                    echo '<a href="' . htmlspecialchars($url) . '" target="_blank">' . htmlspecialchars($url) . '</a><br>';

                    if ($image_data) {
                        foreach ($image_data as $image_info) {
                            echo 'Grafika: <a href="' . htmlspecialchars($image_info['src']) . '" target="_blank">' . htmlspecialchars($image_info['src']) . '</a><br>';
                            echo 'Wymiary wyświetlane: ' . htmlspecialchars($image_info['display_width'] ?: 'Nieznane') . 'x' . htmlspecialchars($image_info['display_height'] ?: 'Nieznane') . 'px<br>';
                            echo 'Rzeczywiste wymiary: ' . htmlspecialchars($image_info['real_width']) . 'x' . htmlspecialchars($image_info['real_height']) . 'px<br><br>';
                        }
                    } else {
                        echo '<div class="alert alert-warning">Nie znaleziono grafik w sekcji "blog" lub nie udało się pobrać ich wymiarów.</div>';
                    }
                    echo '</li>';
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
