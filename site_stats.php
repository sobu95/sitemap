<?php
session_start();
require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest administratorem
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

// Pobieranie statystyk
// Liczba użytkowników
$stmt = $conn->prepare("SELECT COUNT(*) AS user_count FROM users");
$stmt->execute();
$result = $stmt->get_result();
$user_count = $result->fetch_assoc()['user_count'];

// Liczba domen
$stmt = $conn->prepare("SELECT COUNT(*) AS domain_count FROM domains");
$stmt->execute();
$result = $stmt->get_result();
$domain_count = $result->fetch_assoc()['domain_count'];

// Liczba sprawdzeń URL-i
$stmt = $conn->prepare("SELECT COUNT(*) AS check_count FROM domain_checks");
$stmt->execute();
$result = $stmt->get_result();
$check_count = $result->fetch_assoc()['check_count'];

// Ostatnie uruchomienie CRON
$stmt = $conn->prepare("SELECT MAX(checked_at) AS last_cron FROM domain_checks");
$stmt->execute();
$result = $stmt->get_result();
$last_cron = $result->fetch_assoc()['last_cron'];

?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Statystyki systemu'; include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Statystyki systemu</h1>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Statystyka</th>
                        <th>Wartość</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Liczba użytkowników</td>
                        <td><?= htmlspecialchars($user_count) ?></td>
                    </tr>
                    <tr>
                        <td>Liczba domen</td>
                        <td><?= htmlspecialchars($domain_count) ?></td>
                    </tr>
                    <tr>
                        <td>Liczba sprawdzeń URL-i</td>
                        <td><?= htmlspecialchars($check_count) ?></td>
                    </tr>
                    <tr>
                        <td>Ostatnie uruchomienie CRON</td>
                        <td><?= $last_cron ? htmlspecialchars($last_cron) : 'Brak danych' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Powrót do panelu administratora -->
    <div class="row justify-content-center mt-3">
        <div class="col-md-8 text-center">
            <a href="admin.php" class="btn btn-secondary">Powrót do panelu administratora</a>
        </div>
    </div>
</div>

</body>
</html>