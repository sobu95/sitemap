<?php
// profile.php — Zarządzanie profilem użytkownika (zmiana hasła, e-maila)
require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Pobieramy ID zalogowanego użytkownika
$username = $_SESSION['username']; // Pobieramy nazwę użytkownika
$role = $_SESSION['role']; // Rola użytkownika (admin/user)

$error = '';
$success = '';

// Pobieranie bieżących danych użytkownika
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$current_email = $user['email'];


// Obsługa zmiany hasła
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Pobieranie bieżącego hasła użytkownika z bazy danych
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Sprawdzanie, czy aktualne hasło jest poprawne
    if (password_verify($current_password, $user['password'])) {
        // Sprawdzanie, czy nowe hasło i potwierdzenie się zgadzają
        if ($new_password === $confirm_password) {
            // Szyfrowanie nowego hasła
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Aktualizacja hasła w bazie danych
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashed_password, $user_id);

            if ($stmt->execute()) {
                log_activity($user_id, "Zmieniono hasło.");
                $success = 'Hasło zostało pomyślnie zmienione.';
            } else {
                $error = 'Wystąpił błąd podczas zmiany hasła.';
            }
        } else {
            $error = 'Nowe hasło i potwierdzenie hasła nie zgadzają się.';
        }
    } else {
        $error = 'Bieżące hasło jest niepoprawne.';
    }
}

// Obsługa zmiany e-maila
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $new_email = $_POST['new_email'];

    // Sprawdzanie, czy nowy e-mail jest prawidłowy
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nieprawidłowy adres e-mail.';
    } else {
        // Sprawdzanie, czy e-mail nie jest już używany przez innego użytkownika
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $new_email, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Ten adres e-mail jest już używany przez innego użytkownika.';
        } else {
            // Aktualizacja e-maila w bazie danych
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param('si', $new_email, $user_id);

            if ($stmt->execute()) {
                log_activity($user_id, "Zmieniono adres e-mail.");
                $success = 'Adres e-mail został pomyślnie zmieniony.';
                $current_email = $new_email; // Aktualizacja bieżącego e-maila na stronie
            } else {
                $error = 'Wystąpił błąd podczas zmiany adresu e-mail.';
            }
        }
    }
}


?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Twój profil'; include 'inc/head.php'; ?>
<body>
    <?php include('inc/sidebar.php'); ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
<div class="container mt-5">
    <h1 class="text-center mb-4">Twój profil</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8">

            <!-- Informacje o użytkowniku -->
            <h3>Informacje o koncie</h3>
            <p><strong>Użytkownik:</strong> <?= htmlspecialchars($username) ?></p>
            <p><strong>Rola:</strong> <?= htmlspecialchars($role) ?></p>
            <p><strong>E-mail:</strong> <?= htmlspecialchars($current_email) ?></p>

            <hr>

            <!-- Formularz zmiany hasła -->
            <h3>Zmień hasło</h3>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="current_password" class="form-label">Bieżące hasło</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">Nowe hasło</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Potwierdź nowe hasło</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary w-100">Zmień hasło</button>
            </form>

            <hr>

            <!-- Formularz zmiany e-maila -->
            <h3>Zmień adres e-mail</h3>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="new_email" class="form-label">Nowy adres e-mail</label>
                    <input type="email" class="form-control" id="new_email" name="new_email" value="<?= htmlspecialchars($current_email) ?>" required>
                </div>
                <button type="submit" name="change_email" class="btn btn-primary w-100">Zmień e-mail</button>
            </form>

            <hr>

        </div>
    </div>
</div>
    </main>

</body>
</html>
