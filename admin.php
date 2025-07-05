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

// 1. Lista domen do sprawdzenia przy kolejnym crawlu
$limit = 10; // Liczba domen na stronę
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
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT d.id) as total_domains
    FROM domains d 
    LEFT JOIN domain_checks dc ON dc.domain_id = d.id 
    WHERE dc.checked_at IS NULL OR dc.checked_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
");
$stmt->bind_param('i', $check_interval_days);
$stmt->execute();
$total_domains_result = $stmt->get_result()->fetch_assoc();
$total_domains = $total_domains_result['total_domains'];
$total_pages = ceil($total_domains / $limit);



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

// dodawanie użytkowników, usuwanie, itp. 


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

$stmt = $conn->prepare("SELECT id, domain, competitor FROM domains");
$stmt->execute();
$domains = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);



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

// Przygotowanie zapytania SQL
$stmt = $conn->prepare($query);

// Jeśli wybrano użytkownika, bindowanie parametru
if ($user_filter) {
    $stmt->bind_param('i', $user_filter);
}

$stmt->execute();
$domains = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obsługa zmiany właściciela domeny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_owner'])) {
    $domain_id = intval($_POST['domain_id']);
    $new_user_id = intval($_POST['new_user_id']);

    // Walidacja danych
    if (!is_numeric($domain_id) || !is_numeric($new_user_id)) {
        $error = 'Nieprawidłowe dane.';
    } else {
        // Sprawdzenie, czy domena istnieje
        $stmt = $conn->prepare("SELECT id FROM domains WHERE id = ?");
        $stmt->bind_param('i', $domain_id);
        $stmt->execute();
        $domain_exists = $stmt->get_result()->num_rows > 0;

        // Sprawdzenie, czy użytkownik istnieje
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->bind_param('i', $new_user_id);
        $stmt->execute();
        $user_exists = $stmt->get_result()->num_rows > 0;

        if (!$domain_exists || !$user_exists) {
            $error = 'Domena lub użytkownik nie istnieje.';
        } else {
            // Aktualizacja właściciela domeny
            $stmt = $conn->prepare("UPDATE domains SET user_id = ? WHERE id = ?");
            $stmt->bind_param('ii', $new_user_id, $domain_id);

            if ($stmt->execute()) {
                $success = 'Właściciel domeny został zmieniony.';
                // Odświeżenie listy domen po zmianie właściciela
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
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Panel administracyjny'; include 'inc/head.php'; ?>
<style>
        .card-header {
            cursor: pointer;
        }
        .card-body {
            display: none;
        }
    </style>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Panel administracyjny</h1>
    <a href="dashboard.php" class="btn btn-secondary">Powrót do panelu użytkownika</a>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Ustawienia systemu -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Ustawienia systemu</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="check_interval_days" class="form-label">Interwał sprawdzania domen (w dniach)</label>
                    <input type="number" class="form-control" id="check_interval_days" name="check_interval_days" value="<?= htmlspecialchars($settings['check_interval_days']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="alert_threshold_percent" class="form-label">Próg procentowy alertu</label>
                    <input type="number" class="form-control" id="alert_threshold_percent" name="alert_threshold_percent" value="<?= htmlspecialchars($settings['alert_threshold_percent']) ?>" required>
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary w-100">Zapisz ustawienia</button>
            </form>
        </div>
    </div>
        <!-- Lista domen do sprawdzenia przy kolejnym crawlu -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Domeny do sprawdzenia przy kolejnym crawlu</h2>
        </div>
        <div class="card-body" id="domainsToCheckCard">
            <table class="table table-bordered">
                <thead>
    <tr>
        <th>Domena</th>
        <th>Ostatnie sprawdzenie</th>
        <th>Kolejne sprawdzenie</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($domains_to_check as $domain): ?>
        <tr>
            <td><?= htmlspecialchars($domain['domain']) ?></td>
            <td><?= htmlspecialchars($domain['last_checked'] ?? 'Nigdy') ?></td>
            <td><?= htmlspecialchars($domain['next_check']) ?></td>
        </tr>
    <?php endforeach; ?>
</tbody>

            </table>
            <nav>
    <ul class="pagination">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page - 1 ?>">Poprzednia</a>
            </li>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page + 1 ?>">Następna</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
        </div>
    </div>

    <!-- Zarządzanie użytkownikami -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Użytkownicy</h2>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
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
                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-warning btn-sm">Edytuj</a>
                                <a href="admin.php?delete_user=<?= $user['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Czy na pewno chcesz usunąć tego użytkownika?')">Usuń</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Formularz dodawania nowego użytkownika -->
            <h3 class="mt-4">Dodaj nowego użytkownika</h3>
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
                    <select class="form-control" id="role" name="role" required>
                        <option value="user">Użytkownik</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary w-100">Dodaj użytkownika</button>
            </form>
        </div>
    </div>

    <!-- Logi aktywności użytkowników -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Logi aktywności użytkowników</h2>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
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
        </div>
    </div>

    <!-- Logi systemowe (sprawdzanie sitemap) -->
    <div class="card mb-4">
        <div class="card-header">
            <h2>Logi systemowe (sprawdzanie sitemap)</h2>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
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
    </div>

    <!-- Lista domen -->
<div class="card mb-4">
    <div class="card-header">
        <h2>Lista domen</h2>
    </div>
    <div class="card-body" id="domainsCard">
        <form method="GET" action="">
    <div class="mb-3">
    <div class="d-flex align-items-end">
    <div class="flex-grow-1">
        <label for="user_filter" class="form-label">Wybierz użytkownika</label>
        <select class="form-control" id="user_filter" name="user_id">
            <option value="">Wszyscy użytkownicy</option>
            <?php foreach ($users_list as $user): ?>
                <option value="<?= htmlspecialchars($user['id']) ?>" <?= isset($_GET['user_id']) && $_GET['user_id'] == $user['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['username']) ?> (Dodane domeny: <?= htmlspecialchars($user['domain_count']) ?>) [ID: <?= htmlspecialchars($user['id']) ?>]
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary ms-3">Filtruj</button>
</div>

</form>

        <table class="table table-bordered" style="margin-top: 25px;">
            <thead>
                <tr>
                    <th>Domena</th>
                    <th>Typ</th> <!-- Własna/konkurencja -->
                    <th>Właściciel</th> <!-- Dodana kolumna właściciela -->
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($domains as $domain): ?>
                    <tr>
                        <td><?= htmlspecialchars($domain['domain']) ?></td>
                        <td><?= $domain['competitor'] ? 'Konkurencja' : 'Własna' ?></td>
                        <td><?= htmlspecialchars($domain['username']) ?></td> <!-- Wyświetlanie właściciela -->
                        <td>
                            <a href="force_check.php?id=<?= $domain['id'] ?>" class="btn btn-primary btn-sm">Wymuś sprawdzenie</a>
                            <!-- Formularz zmiany właściciela -->
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#changeOwnerModal<?= $domain['id'] ?>">Zmień właściciela</button>
                        </td>
                    </tr>
                    <!-- Modal zmiany właściciela -->
                    <div class="modal fade" id="changeOwnerModal<?= $domain['id'] ?>" tabindex="-1" aria-labelledby="changeOwnerModalLabel<?= $domain['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="changeOwnerModalLabel<?= $domain['id'] ?>">Zmień właściciela domeny: <?= htmlspecialchars($domain['domain']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="domain_id" value="<?= $domain['id'] ?>">
                                        <div class="mb-3">
                                            <label for="new_user_id" class="form-label">Wybierz nowego właściciela</label>
                                            <select class="form-control" id="new_user_id" name="new_user_id" required>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= $domain['user_id'] == $user['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($user['username']) ?> (ID: <?= htmlspecialchars($user['id']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="change_owner" class="btn btn-primary w-100">Zmień właściciela</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
    document.querySelectorAll('.card-header').forEach(header => {
        header.addEventListener('click', function() {
            const body = this.nextElementSibling;
            if (body.style.display === 'none' || body.style.display === '') {
                body.style.display = 'block';
            } else {
                body.style.display = 'none';
            }
        });
    });

   // Funkcja do sprawdzania obecności parametru w URL
function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.has(name);
}

// Jeśli parametr 'page' istnieje w URL, otwórz sekcję "Domeny do sprawdzenia przy kolejnym crawlu"
if (getUrlParameter('page')) {
    const domainsToCheckCard = document.getElementById('domainsToCheckCard');
    domainsToCheckCard.style.display = 'block';
}

// Jeśli parametr 'user_id' istnieje w URL, otwórz sekcję "Lista domen"
if (getUrlParameter('user_id')) {
    const domainsCard = document.getElementById('domainsCard');
    domainsCard.style.display = 'block';
}
</script>

</body>
</html>