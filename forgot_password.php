<?php
// forgot_password.php — Strona do resetowania hasła przez e-mail
require 'db.php'; // Połączenie z bazą danych

$error = '';
$success = '';

// Funkcja do generowania dynamicznego URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = pathinfo($script, PATHINFO_DIRNAME);
    return $protocol . $host . $path;
}

// Obsługa formularza resetu hasła
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];

    // Sprawdzenie, czy e-mail istnieje w bazie danych
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Tworzenie unikalnego tokena do resetu hasła
        $token = bin2hex(random_bytes(50)); // Generowanie losowego tokena
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        // Zapisanie tokena do bazy danych z określoną datą wygaśnięcia (np. 1 godzina)
        $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $user_id, $token, $expiry_time);
        if ($stmt->execute()) {
            // Generowanie dynamicznego linku do resetu hasła
            $base_url = getBaseUrl();  // Pobieranie podstawowego adresu URL
            $reset_link = $base_url . "/reset_password.php?token=" . $token;

            // Wysłanie e-maila do użytkownika z linkiem do resetu hasła
            $subject = "Resetowanie hasła w systemie";
            $message = "
            Witaj,

            Otrzymaliśmy prośbę o zresetowanie Twojego hasła. Kliknij poniższy link, aby zresetować hasło:
            
            $reset_link

            Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość.

            Link jest ważny przez 1 godzinę.

           ";

            // Wysyłanie wiadomości e-mail
            if (mail($email, $subject, $message)) {
                $success = 'Na Twój adres e-mail wysłano link do resetu hasła.';
            } else {
                $error = 'Wystąpił błąd podczas wysyłania wiadomości e-mail. Spróbuj ponownie później.';
            }
        } else {
            $error = 'Wystąpił błąd podczas zapisu danych do bazy. Spróbuj ponownie później.';
        }
    } else {
        $error = 'Nie znaleziono użytkownika z podanym adresem e-mail.';
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'SiteMap Checker - Resetowanie hasła'; include 'inc/head.php'; ?>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">SiteMap Checker</div>
        <h1 class="auth-title">Zapomniałeś hasła?</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">Podaj swój adres e-mail</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-paper-plane"></i>
                Wyślij link do resetu hasła
            </button>
        </form>

        <div class="auth-link">
            Pamiętasz hasło? <a href="index.php">Zaloguj się</a>
        </div>
    </div>
</div>

</body>
</html>