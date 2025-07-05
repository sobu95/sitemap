<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// dashboard.php — Panel użytkownika z logowaniem aktywności, zarządzaniem domenami i sitemapami
require '../auth.php';
require '../db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Pobieramy ID zalogowanego użytkownika
$username = $_SESSION['username']; // Pobieramy nazwę użytkownika
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Ankor-PukSoft - Status checker'; include '../inc/head.php'; ?>
<body>

<div class="container">
    <?php include('../inc/sidebar.php'); ?>

    <main class="main-content">
        <div class="breadcrumb">
            <a href="../dashboard.php">Domeny</a>
            <span class="breadcrumb-separator">/</span>
            <span>Status Code Checker</span>
        </div>

        <header class="header">
            <h1>Sprawdzanie statusu URL-i</h1>
        </header>

        <div class="content-panel">
            <!-- Formularz HTML do wprowadzenia danych -->
            <form method="post" id="url-form">
                <div class="form-group">
                    <label for="urls" class="form-label">Wprowadź adresy URL (jeden na linię, max 10):</label>
                    <textarea class="form-control" id="urls" name="urls" rows="10" placeholder="https://example.com&#10;https://example2.com&#10;..."></textarea>
                </div>
                <div class="form-group">
                    <label for="rate_limit" class="form-label">Czy system ma rate-limiting?</label>
                    <select class="form-control form-select" id="rate_limit" name="rate_limit">
                        <option value="no">Nie</option>
                        <option value="yes">Tak</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-play"></i>
                    Rozpocznij skanowanie
                </button>
            </form>
        </div>

        <!-- Miejsce na komunikat -->
        <div id="scan-message" class="content-panel" style="display: none;">
            <div style="text-align: center;">
                <div class="spinner"></div>
                <p style="margin-top: 1rem;">Trwa sprawdzanie domen...</p>
            </div>
        </div>

        <!-- Miejsce na wyniki -->
        <div id="results" class="content-panel" style="display: none;">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-chart-line"></i>
                Wyniki sprawdzenia
            </h3>
            <table class="domain-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Status</th>
                        <th>Przekierowanie</th>
                    </tr>
                </thead>
                <tbody id="results-body"></tbody>
            </table>
            <p id="remaining-urls" style="margin-top: 1rem; color: var(--text-dark);"></p>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $('#url-form').submit(function(event) {
            event.preventDefault();
            
            // Pobierz adresy URL z pola tekstowego
            let urls = $('#urls').val().trim();
            let urlList = urls.split('\n').filter(url => url.trim() !== ''); // Usuń puste linie

            // Sprawdź, czy liczba adresów URL nie przekracza 10
            if (urlList.length > 10) {
                alert('Możesz wprowadzić maksymalnie 10 adresów URL.');
                return false; // Zatrzymanie dalszego wykonywania kodu
            }

            // Pokaż komunikat o skanowaniu i ukryj wyniki
            $('#scan-message').show();
            $('#results').hide();
            $('#results-body').html(''); // Wyczyść poprzednie wyniki

            // Wysłanie zapytania AJAX do skryptu PHP
            let rateLimit = $('#rate_limit').val();
            $.post('url_checker.php', { urls: urls, rate_limit: rateLimit }, function(response) {
                $('#scan-message').hide();
                $('#results').show();
                $('#results-body').html(response.results_html);
                $('#remaining-urls').text('Pozostało ' + response.remaining_urls + ' adresów URL do sprawdzenia');
            }, 'json');
        });
    });
</script>
</body>
</html>