<?php
session_start();
require 'auth.php';
require 'db.php';

// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sprawdzenie, czy użytkownik jest administratorem
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Funkcja usuwająca domeny powiązane z użytkownikiem
function delete_user_domains($user_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM domains WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}

// Obsługa edycji użytkownika
$edit_mode = false;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_mode = true;
    $user_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $role = $_POST['role'];
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param('si', $role, $user_id);
        if ($stmt->execute()) {
            $success = 'Rola użytkownika została zaktualizowana.';
        } else {
            $error = 'Wystąpił błąd podczas aktualizacji użytkownika.';
        }
    }
}

// Obsługa formularza dodawania użytkownika
if (!$edit_mode && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['email'], $_POST['password'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'user';

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = 'Użytkownik o tej nazwie lub emailu już istnieje!';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $username, $hashed_password, $email, $role);
        if ($stmt->execute()) {
            $success = 'Użytkownik został pomyślnie dodany!';
        } else {
            $error = 'Wystąpił błąd podczas dodawania użytkownika.';
        }
    }
}

// Usuwanie użytkownika
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!is_admin()) {
        $error = 'Brak uprawnień do usunięcia użytkownika.';
    } else {
        $user_id = $_GET['delete'];
        if ($user_id == 1) {
            $error = 'Nie możesz usunąć użytkownika o ID=1.';
        } else {
            delete_user_domains($user_id, $conn);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            if ($stmt->execute()) {
                $success = 'Użytkownik oraz powiązane dane zostały usunięte.';
            } else {
                $error = 'Wystąpił błąd podczas usuwania użytkownika.';
            }
        }
    }
}

// Pobieranie listy użytkowników
$stmt = $conn->prepare("SELECT id, username, email, role FROM users");
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Zarządzaj użytkownikami'; include 'inc/head.php'; ?>
    <style>
        #add-user-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
        }
    </style>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Zarządzaj użytkownikami</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$edit_mode): ?>
    <div class="row justify-content-center mb-4">
        <div class="col-md-6 text-center">
            <button id="toggle-add-user-form" class="btn btn-primary">Dodawanie użytkownika</button>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div id="add-user-form">
        <h2 class="mb-3">Dodaj nowego użytkownika</h2>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Nazwa użytkownika</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Hasło</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Rola</label>
                <select class="form-control" id="role" name="role">
                    <option value="user">Użytkownik</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Dodaj użytkownika</button>
            <button type="button" id="cancel-add-user" class="btn btn-secondary">Anuluj</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($edit_mode): ?>
    <div class="row justify-content-center mb-4">
        <div class="col-md-6">
            <h2 class="mb-3">Edytuj użytkownika</h2>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Nazwa użytkownika</label>
                    <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="role" class="form-label">Rola</label>
                    <select class="form-control" id="role" name="role">
                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Użytkownik</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2 class="mb-4">Lista użytkowników</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa użytkownika</th>
                        <th>Email</th>
                        <th>Rola</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td>
                                <a href="?edit=<?= $user['id'] ?>" class="btn btn-warning btn-sm">Edytuj</a>
                                <?php if ($user['id'] != 1): ?>
                                    <a href="#" class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $user['id'] ?>)">Usuń</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row justify-content-center mt-3">
        <div class="col-md-8 text-center">
            <a href="admin.php" class="btn btn-secondary">Powrót do panelu administratora</a>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId) {
    if (confirm('Czy na pewno chcesz usunąć tego użytkownika?')) {
        window.location.href = '?delete=' + userId;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleAddUserFormButton = document.getElementById('toggle-add-user-form');
    const addUserForm = document.getElementById('add-user-form');
    const cancelAddUserButton = document.getElementById('cancel-add-user');
    const overlay = document.getElementById('overlay');

    if (toggleAddUserFormButton) {
        toggleAddUserFormButton.addEventListener('click', function() {
            addUserForm.style.display = 'block';
            overlay.style.display = 'block';
        });
    }

    if (cancelAddUserButton) {
        cancelAddUserButton.addEventListener('click', function() {
            addUserForm.style.display = 'none';
            overlay.style.display = 'none';
        });
    }

    overlay.addEventListener('click', function() {
        addUserForm.style.display = 'none';
        overlay.style.display = 'none';
    });
});
</script>
</body>
</html>