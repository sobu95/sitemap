<?php
// register.php — Strona rejestracji użytkownika
require 'auth.php';

if (is_logged_in()) {
    // Jeśli użytkownik jest zalogowany, przekieruj na stronę główną (np. dashboard.php)
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];

    if (register($username, $password, $email)) {
        // Rejestracja powiodła się, przekieruj na stronę logowania
        header('Location: index.php');
        exit();
    } else {
        $error = 'Nazwa użytkownika lub e-mail jest już zajęty!';
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'SiteMap Checker - Rejestracja'; include 'inc/head.php'; ?>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">SiteMap Checker</div>
        <h1 class="auth-title">Rejestracja</h1>

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
            <div class="form-group">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-user-plus"></i>
                Zarejestruj się
            </button>
        </form>

        <div class="auth-link">
            Masz już konto? <a href="index.php">Zaloguj się</a>
        </div>
    </div>
</div>

</body>
</html>