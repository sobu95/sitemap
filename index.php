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
<?php $page_title = 'Ankor-PukSoft - Logowanie'; include 'inc/head.php'; ?>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">Ankor-PukSoft</div>
        <h1 class="auth-title">Zaloguj się</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">Nazwa użytkownika</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Hasło</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-sign-in-alt"></i>
                Zaloguj się
            </button>
        </form>

        <div class="auth-link">
            Zapomniałeś hasła? <a href="forgot_password.php">Przypomnij hasło</a>
        </div>

        <div class="auth-link">
            Nie masz konta? <a href="register.php">Zarejestruj się</a>
        </div>
    </div>
</div>

</body>
</html>