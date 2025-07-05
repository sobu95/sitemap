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
<?php $page_title = 'Status checker'; include '../inc/head.php'; ?>
<body>

    <?php include('../inc/sidebar.php'); ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="container">
    <h1>Sprawdzanie statusu URL-i</h1>
    <!-- Formularz HTML do wprowadzenia danych -->
    <form method="post" id="url-form">
        <div class="mb-3">
            <label for="urls" class="form-label">Wprowadź adresy URL (jeden na linię, max 10):</label>
            <textarea class="form-control" id="urls" name="urls" rows="10" placeholder="Wprowadź adresy URL"></textarea>
        </div>
        <div class="mb-3">
            <label for="rate_limit" class="form-label">Czy system ma rate-limiting?</label>
            <select class="form-control" id="rate_limit" name="rate_limit">
                <option value="no">Nie</option>
                <option value="yes">Tak</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Rozpocznij skanowanie</button>
    </form>

    <!-- Miejsce na komunikat -->
    <div id="scan-message" class="mt-5" style="display: none;">
        <p>Trwa sprawdzanie domen...</p>
    </div>

    <!-- Miejsce na wyniki -->
    <div id="results" class="mt-5" style="display: none;">
        <h3>Wynik:</h3>
        <table class="table table-striped">
            <thead><tr><th>URL</th><th>Status</th><th>Przekierowanie</th></tr></thead>
            <tbody id="results-body"></tbody>
        </table>
        <p id="remaining-urls">Pozostało X adresów URL do sprawdzenia</p>
    </div>
</main>

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
