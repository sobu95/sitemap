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
<?php $page_title = 'Rejestracja'; include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Rejestracja</h1>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Nazwa użytkownika</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Hasło</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Zarejestruj się</button>
            </form>
            <p class="mt-3 text-center">
                Masz już konto? <a href="index.php">Zaloguj się</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>