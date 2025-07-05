<?php
require 'auth.php'; // Plik autoryzacyjny
require 'db.php';   // Połączenie z bazą danych

// Sprawdzenie, czy użytkownik jest zalogowany jako admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Pobieranie ID użytkownika z parametru URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    $error = 'Nieprawidłowy ID użytkownika.';
}

// Pobieranie danych użytkownika z bazy
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $error = 'Nie znaleziono użytkownika o podanym ID.';
} else {
    $user = $result->fetch_assoc();
}

// Obsługa formularza edycji użytkownika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = isset($_POST['password']) && !empty($_POST['password']) ? $_POST['password'] : null;

    // Walidacja danych
    if (empty($username) || empty($email)) {
        $error = 'Wszystkie pola (oprócz hasła) są wymagane.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nieprawidłowy adres e-mail.';
    } elseif (!in_array($role, ['admin', 'user'])) {
        $error = 'Nieprawidłowa rola użytkownika.';
    } else {
        // Aktualizacja użytkownika
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $username, $email, $role, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param('sssi', $username, $email, $role, $user_id);
        }

        if ($stmt->execute()) {
            $success = 'Dane użytkownika zostały zaktualizowane.';
            // Odśwież dane użytkownika po aktualizacji
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Wystąpił błąd podczas aktualizacji użytkownika.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Edycja użytkownika'; include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Edycja użytkownika: <?= htmlspecialchars($user['username']) ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Nazwa użytkownika</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">Rola użytkownika</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Użytkownik</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Nowe hasło (pozostaw puste, aby nie zmieniać)</label>
                    <input type="password" class="form-control" id="password" name="password">
                </div>
                <button type="submit" name="update_user" class="btn btn-primary w-100">Zaktualizuj użytkownika</button>
            </form>
        </div>
    </div>

    <!-- Powrót do panelu admina -->
    <div class="text-center mt-4">
        <a href="admin.php" class="btn btn-secondary">Powrót do panelu admina</a>
    </div>
</div>

</body>
</html>
