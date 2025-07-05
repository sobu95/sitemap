<?php
// Włącz wyświetlanie błędów (umieść na górze pliku)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// index.php — Strona logowania
require 'auth.php';

// Jeśli użytkownik jest już zalogowany, przekierowanie na dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

// Obsługa formularza logowania
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Sprawdzanie danych logowania
    if (login($username, $password)) {
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Nieprawidłowa nazwa użytkownika lub hasło!';
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Logowanie'; include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Zaloguj się</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Nazwa użytkownika</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Hasło</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Zaloguj się</button>
            </form>
        </div>
    </div>

    <!-- Link do przypomnienia hasła -->
    <p class="mt-3 text-center">
        Zapomniałeś hasła? <a href="forgot_password.php">Przypomnij hasło</a>
    </p>

    <!-- Link do rejestracji (opcjonalnie) -->
    <p class="mt-3 text-center">
        Nie masz konta? <a href="register.php">Zarejestruj się</a>
    </p>
</div>

</body>
</html>
