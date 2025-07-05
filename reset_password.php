<?php
require 'db.php'; // Połączenie z bazą danych

$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Sprawdzanie tokena
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($token)) {
    // Pobieranie tokena z bazy danych
    $stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $reset_data = $result->fetch_assoc();
        $expires_at = $reset_data['expires_at'];
        $user_id = $reset_data['user_id'];

        // Sprawdzanie, czy token wygasł
        if (strtotime($expires_at) < time()) {
            $error = 'Link do resetowania hasła wygasł. Proszę spróbować ponownie.';
        }
    } else {
        $error = 'Nieprawidłowy token resetu hasła. Proszę spróbować ponownie.';
    }
}

// Obsługa zmiany hasła
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && isset($_POST['password_confirm'])) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $token = $_POST['token'];

    if (empty($password) || empty($password_confirm)) {
        $error = 'Proszę wypełnić oba pola hasła.';
    } elseif ($password !== $password_confirm) {
        $error = 'Podane hasła się nie zgadzają.';
    } else {
        // Sprawdzanie, czy token nadal istnieje i jest ważny
        $stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $reset_data = $result->fetch_assoc();
            $user_id = $reset_data['user_id'];
            $expires_at = $reset_data['expires_at'];

            if (strtotime($expires_at) >= time()) {
                // Hashowanie hasła i aktualizacja w bazie danych
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param('si', $hashed_password, $user_id);

                if ($stmt->execute()) {
                    // Usuwanie tokena po użyciu
                    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                    $stmt->bind_param('s', $token);
                    $stmt->execute();

                    $success = 'Hasło zostało pomyślnie zmienione. Możesz teraz się <a href="index.php">zalogować</a>.';
                } else {
                    $error = 'Wystąpił błąd podczas aktualizacji hasła. Proszę spróbować ponownie.';
                }
            } else {
                $error = 'Link do resetowania hasła wygasł.';
            }
        } else {
            $error = 'Nieprawidłowy token resetu hasła. Proszę spróbować ponownie.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Resetowanie hasła'; include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Resetowanie hasła</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php else: ?>
        <?php if (!empty($token)): ?>
            <!-- Formularz resetu hasła -->
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <form method="POST" action="">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nowe hasło</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Potwierdź nowe hasło</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Zresetuj hasło</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <p>Nieprawidłowy lub wygasły token resetu hasła.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>