<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'auth.php';
require 'db.php';

if (!is_admin()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

$stmt = $conn->prepare("SELECT check_interval_days, alert_threshold_percent FROM settings LIMIT 1");
$stmt->execute();
$settings_result = $stmt->get_result();
$settings = $settings_result->fetch_assoc();

if (!$settings) {
    $settings = [
        'check_interval_days' => 7,
        'alert_threshold_percent' => 10
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $check_interval_days = $_POST['check_interval_days'];
    $alert_threshold_percent = $_POST['alert_threshold_percent'];

    $stmt = $conn->prepare("SELECT id FROM settings LIMIT 1");
    $stmt->execute();
    $settings_exist = $stmt->get_result()->num_rows > 0;

    if ($settings_exist) {
        $stmt = $conn->prepare("UPDATE settings SET check_interval_days = ?, alert_threshold_percent = ?");
        $stmt->bind_param('ii', $check_interval_days, $alert_threshold_percent);
    } else {
        $stmt = $conn->prepare("INSERT INTO settings (check_interval_days, alert_threshold_percent) VALUES (?, ?)");
        $stmt->bind_param('ii', $check_interval_days, $alert_threshold_percent);
    }

    if ($stmt->execute()) {
        $success = 'Ustawienia systemowe zostały zaktualizowane.';
        $settings['check_interval_days'] = $check_interval_days;
        $settings['alert_threshold_percent'] = $alert_threshold_percent;
    } else {
        $error = 'Wystąpił błąd podczas aktualizacji ustawień.';
    }
}

// Pobieranie statystyk
$stmt = $conn->prepare("SELECT COUNT(*) AS user_count FROM users");
$stmt->execute();
$user_count = $stmt->get_result()->fetch_assoc()['user_count'];

$stmt = $conn->prepare("SELECT COUNT(*) AS domain_count FROM domains");
$stmt->execute();
$domain_count = $stmt->get_result()->fetch_assoc()['domain_count'];

$stmt = $conn->prepare("SELECT COUNT(*) AS check_count FROM domain_checks");
$stmt->execute();
$check_count = $stmt->get_result()->fetch_assoc()['check_count'];

// Lista domen do sprawdzenia przy kolejnym crawlu
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$check_interval_days = intval($settings['check_interval_days']);
$stmt = $conn->prepare("
    SELECT d.id, d.domain, MAX(dc.checked_at) as last_checked,
           IF(MAX(dc.checked_at) IS NULL, NOW(), DATE_ADD(MAX(dc.checked_at), INTERVAL ? DAY)) as next_check
    FROM domains d 
    LEFT JOIN domain_checks dc ON dc.domain_id = d.id 
    GROUP BY d.id, d.domain 
    ORDER BY last_checked ASC
    LIMIT ? OFFSET ?
");
$stmt->bind_param('iii', $check_interval_days, $limit, $offset);
$stmt->execute();
$domains_to_check = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pobierz listę użytkowników z liczbą domen
$stmt = $conn->prepare("
    SELECT u.id, u.username, COUNT(d.id) AS domain_count
    FROM users u
    LEFT JOIN domains d ON u.id = d.user_id
    GROUP BY u.id, u.username
    ORDER BY u.id ASC
");
$stmt->execute();
$users_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Dodawanie użytkowników
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nieprawidłowy adres e-mail.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Użytkownik z tym adresem e-mail już istnieje.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $username, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $success = 'Użytkownik został dodany pomyślnie.';
            } else {
                $error = 'Wystąpił błąd podczas dodawania użytkownika.';
            }
        }
    }
}

if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);

    if ($stmt->execute()) {
        $success = 'Użytkownik został usunięty.';
    } else {
        $error = 'Wystąpił błąd podczas usuwania użytkownika.';
    }
}

$stmt = $conn->prepare("SELECT id, username, email, role FROM users");
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT al.activity, al.created_at, u.username FROM activity_log al JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");
$stmt->execute();
$activity_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT dc.checked_at, dc.result, d.domain 
    FROM domain_checks dc 
    JOIN domains d ON dc.domain_id = d.id 
    ORDER BY dc.checked_at DESC LIMIT 10
");
$stmt->execute();
$system_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pobierz filtr użytkownika
$user_filter = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Zbuduj zapytanie SQL
$query = "
    SELECT d.id, d.domain, d.competitor, u.username, d.user_id
    FROM domains d
    JOIN users u ON d.user_id = u.id
";

if ($user_filter) {
    $query .= " WHERE d.user_id = ?";
}

$query .= " ORDER BY d.domain ASC";

$stmt = $conn->prepare($query);

if ($user_filter) {
    $stmt->bind_param('i', $user_filter);
}

$stmt->execute();
$domains = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obsługa zmiany właściciela domeny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_owner'])) {
    $domain_id = intval($_POST['domain_id']);
    $new_user_id = intval($_POST['new_user_id']);

    if (!is_numeric($domain_id) || !is_numeric($new_user_id)) {
        $error = 'Nieprawidłowe dane.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM domains WHERE id = ?");
        $stmt->bind_param('i', $domain_id);
        $stmt->execute();
        $domain_exists = $stmt->get_result()->num_rows > 0;

        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param('i', $new_user_id);
        $stmt->execute();
        $user_exists = $stmt->get_result()->num_rows > 0;

        if (!$domain_exists || !$user_exists) {
            $error = 'Domena lub użytkownik nie istnieje.';
        } else {
            $stmt = $conn->prepare("UPDATE domains SET user_id = ? WHERE id = ?");
            $stmt->bind_param('ii', $new_user_id, $domain_id);

            if ($stmt->execute()) {
                $success = 'Właściciel domeny został zmieniony.';
                $stmt = $conn->prepare($query);
                if ($user_filter) {
                    $stmt->bind_param('i', $user_filter);
                }
                $stmt->execute();
                $domains = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $error = 'Wystąpił błąd podczas zmiany właściciela domeny.';
            }
        }
    }
}

$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Ankor-PukSoft - Panel administracyjny'; include 'inc/head.php'; ?>
<body>

<div class="container">
    <?php include('inc/sidebar.php'); ?>

    <main class="main-content">
        <header class="header">
            <h1>Panel administracyjny</h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i>
                    Powrót do panelu użytkownika
                </a>
            </div>
        </header>

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

        <!-- Statystyki -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $user_count ?></div>
                <div class="stat-label">
                    <i class="fa-solid fa-users"></i>
                    Użytkownicy
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $domain_count ?></div>
                <div class="stat-label">
                    <i class="fa-solid fa-globe"></i>
                    Domeny
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $check_count ?></div>
                <div class="stat-label">
                    <i class="fa-solid fa-chart-line"></i>
                    Sprawdzenia
                </div>
            </div>
        </div>

        <!-- Ustawienia systemu -->
        <div class="collapsible-header" onclick="toggleSection('settings')">
            <h3>
                <i class="fa-solid fa-cog"></i>
                Ustawienia systemu
            </h3>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="collapsible-content" id="settings">
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="check_interval_days" class="form-label">Interwał sprawdzania domen (w dniach)</label>
                        <input type="number" class="form-control" id="check_interval_days" name="check_interval_days" value="<?= htmlspecialchars($settings['check_interval_days']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="alert_threshold_percent" class="form-label">Próg procentowy alertu</label>
                        <input type="number" class="form-control" id="alert_threshold_percent" name="alert_threshold_percent" value="<?= htmlspecialchars($settings['alert_threshold_percent']) ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i>
                    Zapisz ustawienia
                </button>
            </form>
        </div>

        <!-- Zarządzanie użytkownikami -->
        <div class="collapsible-header" onclick="toggleSection('users')">
            <h3>
                <i class="fa-solid fa-users"></i>
                Użytkownicy
            </h3>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="collapsible-content" id="users">
            <table class="domain-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Użytkownik</th>
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
                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="action-link">
                                    <i class="fa-solid fa-edit"></i>
                                    Edytuj
                                </a>
                                <a href="admin.php?delete_user=<?= $user['id'] ?>" class="action-link action-link-delete" onclick="return confirm('Czy na pewno chcesz usunąć tego użytkownika?')">
                                    <i class="fa-solid fa-trash"></i>
                                    Usuń
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Formularz dodawania nowego użytkownika -->
            <h4 style="margin-top: 2rem; margin-bottom: 1rem;">Dodaj nowego użytkownika</h4>
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="username" class="form-label">Nazwa użytkownika</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">Hasło</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="role" class="form-label">Rola</label>
                        <select class="form-control form-select" id="role" name="role" required>
                            <option value="user">Użytkownik</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    Dodaj użytkownika
                </button>
            </form>
        </div>

        <!-- Lista domen -->
        <div class="collapsible-header" onclick="toggleSection('domains')">
            <h3>
                <i class="fa-solid fa-globe"></i>
                Lista domen
            </h3>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="collapsible-content" id="domains">
            <div class="filter-container">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="user_filter" class="form-label">Wybierz użytkownika</label>
                            <select class="form-control form-select" id="user_filter" name="user_id">
                                <option value="">Wszyscy użytkownicy</option>
                                <?php foreach ($users_list as $user): ?>
                                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= isset($_GET['user_id']) && $_GET['user_id'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['username']) ?> (Domeny: <?= htmlspecialchars($user['domain_count']) ?>) [ID: <?= htmlspecialchars($user['id']) ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-filter"></i>
                                Filtruj
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <table class="domain-table">
                <thead>
                    <tr>
                        <th>Domena</th>
                        <th>Typ</th>
                        <th>Właściciel</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domains as $domain): ?>
                        <tr>
                            <td><?= htmlspecialchars($domain['domain']) ?></td>
                            <td><?= $domain['competitor'] ? 'Konkurencja' : 'Własna' ?></td>
                            <td><?= htmlspecialchars($domain['username']) ?></td>
                            <td>
                                <a href="force_check.php?id=<?= $domain['id'] ?>" class="action-link">
                                    <i class="fa-solid fa-play"></i>
                                    Wymuś sprawdzenie
                                </a>
                                <button type="button" class="action-link" data-bs-toggle="modal" data-bs-target="#changeOwnerModal<?= $domain['id'] ?>">
                                    <i class="fa-solid fa-user-edit"></i>
                                    Zmień właściciela
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Modal zmiany właściciela -->
                        <div class="modal fade" id="changeOwnerModal<?= $domain['id'] ?>" tabindex="-1" aria-labelledby="changeOwnerModalLabel<?= $domain['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="changeOwnerModalLabel<?= $domain['id'] ?>">
                                            <i class="fa-solid fa-user-edit"></i>
                                            Zmień właściciela domeny: <?= htmlspecialchars($domain['domain']) ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" action="">
                                            <input type="hidden" name="domain_id" value="<?= $domain['id'] ?>">
                                            <div class="form-group">
                                                <label for="new_user_id" class="form-label">Wybierz nowego właściciela</label>
                                                <select class="form-control form-select" id="new_user_id" name="new_user_id" required>
                                                    <?php foreach ($users as $user): ?>
                                                        <option value="<?= htmlspecialchars($user['id']) ?>" <?= $domain['user_id'] == $user['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($user['username']) ?> (ID: <?= htmlspecialchars($user['id']) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" name="change_owner" class="btn btn-primary">
                                                <i class="fa-solid fa-save"></i>
                                                Zmień właściciela
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Logi aktywności użytkowników -->
        <div class="collapsible-header" onclick="toggleSection('activity')">
            <h3>
                <i class="fa-solid fa-history"></i>
                Logi aktywności użytkowników
            </h3>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="collapsible-content" id="activity">
            <table class="domain-table">
                <thead>
                    <tr>
                        <th>Użytkownik</th>
                        <th>Aktywność</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity_logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['username']) ?></td>
                            <td><?= htmlspecialchars($log['activity']) ?></td>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="activity_log.php" class="btn btn-secondary">
                    <i class="fa-solid fa-eye"></i>
                    Zobacz wszystkie logi
                </a>
            </div>
        </div>

        <!-- Logi systemowe -->
        <div class="collapsible-header" onclick="toggleSection('system')">
            <h3>
                <i class="fa-solid fa-server"></i>
                Logi systemowe (sprawdzanie sitemap)
            </h3>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="collapsible-content" id="system">
            <table class="domain-table">
                <thead>
                    <tr>
                        <th>Domena</th>
                        <th>Liczba URL-i</th>
                        <th>Data sprawdzenia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($system_logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['domain']) ?></td>
                            <td><?= htmlspecialchars($log['result']) ?></td>
                            <td><?= htmlspecialchars($log['checked_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
function toggleSection(sectionId) {
    const content = document.getElementById(sectionId);
    const header = content.previousElementSibling;
    const icon = header.querySelector('i:last-child');
    
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    } else {
        content.classList.add('show');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    }
}

// Funkcja do sprawdzania obecności parametru w URL
function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.has(name);
}

// Jeśli parametr 'page' istnieje w URL, otwórz sekcję "Domeny do sprawdzenia przy kolejnym crawlu"
if (getUrlParameter('page')) {
    document.getElementById('domainsToCheck').classList.add('show');
}

// Jeśli parametr 'user_id' istnieje w URL, otwórz sekcję "Lista domen"
if (getUrlParameter('user_id')) {
    document.getElementById('domains').classList.add('show');
}
</script>

</body>
</html>