<?php
// Włącz wyświetlanie błędów (umieść na górze pliku)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// dashboard.php — Panel użytkownika z logowaniem aktywności, zarządzaniem domenami i sitemapami
require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Pobieramy ID zalogowanego użytkownika
$username = $_SESSION['username']; // Pobieramy nazwę użytkownika

// Sortowanie
$sort_by = $_GET['sort_by'] ?? 'domain'; // Domyślne sortowanie po domenie
$sort_order = $_GET['sort_order'] ?? 'ASC'; // Domyślna kolejność rosnąca

$error = '';
$success = '';

// Obsługa zmiany hasła
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
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

// Obsługa formularza dodawania domeny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domain'])) {
    $domain = $_POST['domain'];

    if (!filter_var($domain, FILTER_VALIDATE_URL)) {
        $error = 'Nieprawidłowy URL!';
    } else {
        // Sprawdzenie, czy domena już istnieje w bazie dla tego użytkownika
        $stmt = $conn->prepare("SELECT id FROM domains WHERE domain = ? AND user_id = ?");
        $stmt->bind_param('si', $domain, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Ta domena została już dodana!';
        } else {
            // Dodanie domeny do bazy danych
            $stmt = $conn->prepare("INSERT INTO domains (user_id, domain) VALUES (?, ?)");
            $stmt->bind_param('is', $user_id, $domain);

            if ($stmt->execute()) {
                log_activity($user_id, "Dodał domenę: $domain");
                $success = 'Domena została dodana pomyślnie!';
            } else {
                $error = 'Wystąpił błąd podczas dodawania domeny.';
            }
        }
    }
}

// Obsługa usuwania domeny
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $domain_id = $_GET['delete'];

    $stmt = $conn->prepare("SELECT domain FROM domains WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $domain_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $domain = $result->fetch_assoc()['domain'];

        $stmt = $conn->prepare("DELETE FROM domains WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $domain_id, $user_id);

        if ($stmt->execute()) {
            log_activity($user_id, "Usunął domenę: $domain");
            $success = 'Domena została usunięta.';
        } else {
            $error = 'Wystąpił błąd podczas usuwania domeny.';
        }
    } else {
        $error = 'Nie znaleziono domeny do usunięcia.';
    }
}

// Budowanie zapytania SQL z uwzględnieniem sortowania
$order_by_clause = '';
switch ($sort_by) {
    case 'domain':
        $order_by_clause = 'ORDER BY d.domain ' . $sort_order;
        break;
    case 'url_count':
        $order_by_clause = 'ORDER BY CAST(last_check AS UNSIGNED) ' . $sort_order;
        break;
    case 'change':
        $order_by_clause = 'ORDER BY (COALESCE(last_check, 0) - COALESCE(previous_check, 0)) ' . $sort_order;
        break;
    default:
        $order_by_clause = 'ORDER BY d.domain ASC';
}

// Pobieranie listy domen użytkownika wraz z ostatnim wynikiem sprawdzenia URL-i
$sql = "
    SELECT d.id, d.domain, d.created_at, 
           (SELECT result FROM domain_checks WHERE domain_id = d.id ORDER BY checked_at DESC LIMIT 1) AS last_check,
           (SELECT result FROM domain_checks WHERE domain_id = d.id ORDER BY checked_at DESC LIMIT 1 OFFSET 1) AS previous_check
    FROM domains d 
    WHERE d.user_id = ? AND d.competitor = 0
    " . $order_by_clause;

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$domains = $result->fetch_all(MYSQLI_ASSOC);

// Funkcja do uproszczenia nazwy domeny
function simplifyDomain($url) {
    $parsed_url = parse_url($url);
    $domain = isset($parsed_url['host']) ? $parsed_url['host'] : $parsed_url['path'];
    return preg_replace('/^www\./', '', $domain);
}

function getSortURL($sort_by, $current_sort_order) {
    $order = ($current_sort_order == 'ASC') ? 'DESC' : 'ASC';
    return $_SERVER['PHP_SELF'] . '?sort_by=' . $sort_by . '&sort_order=' . $order;
}
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Ankor-PukSoft - Twoje domeny'; include 'inc/head.php'; ?>
<body>

<div class="container">
    <?php include('inc/sidebar.php'); ?>

    <main class="main-content">
        <header class="header">
            <h1>Twoje domeny</h1>
            <div class="header-actions">
                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="fa-solid fa-key"></i>
                    Zmień hasło
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDomainModal">
                    <i class="fa-solid fa-plus"></i>
                    Dodaj domenę
                </button>
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

        <div class="content-panel">
            <table class="domain-table">
                <thead>
                    <tr>
                        <th>
                            <a href="<?= getSortURL('domain', $sort_order) ?>" style="color: var(--text-dark); text-decoration: none;">
                                Domena
                                <?php if ($sort_by == 'domain'): ?>
                                    <i class="fa-solid fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortURL('url_count', $sort_order) ?>" style="color: var(--text-dark); text-decoration: none;">
                                Liczba URL-i
                                <?php if ($sort_by == 'url_count'): ?>
                                    <i class="fa-solid fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortURL('change', $sort_order) ?>" style="color: var(--text-dark); text-decoration: none;">
                                Zmiana
                                <?php if ($sort_by == 'change'): ?>
                                    <i class="fa-solid fa-sort-<?= $sort_order == 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($domains) > 0): ?>
                        <?php foreach ($domains as $domain): ?>
                        <tr>
                            <td>
                                <a href="domain.php?id=<?= $domain['id'] ?>" style="color: var(--text-light); text-decoration: none;">
                                    <?= htmlspecialchars(simplifyDomain($domain['domain'])) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($domain['last_check'] ?? 'Brak sprawdzeń') ?></td>
                            <td>
                                <?php
                                $change = intval($domain['last_check']) - intval($domain['previous_check']);
                                if ($change > 0) {
                                    echo "<span class='positive-change'>+$change</span>";
                                } elseif ($change < 0) {
                                    echo "<span class='negative-change'>$change</span>";
                                } else {
                                    echo "0";
                                }
                                ?>
                            </td>
                            <td>
                                <a href="domain.php?id=<?= $domain['id'] ?>" class="action-link">
                                    <i class="fa-solid fa-eye"></i>
                                    Szczegóły
                                </a>
                                <a href="dashboard.php?delete=<?= $domain['id'] ?>" 
                                   class="action-link action-link-delete" 
                                   onclick="return confirm('Czy na pewno chcesz usunąć tę domenę?')">
                                    <i class="fa-solid fa-trash"></i>
                                    Usuń
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-dark); padding: 2rem;">
                                <i class="fa-solid fa-globe" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i><br>
                                Nie masz jeszcze żadnych domen.<br>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDomainModal" style="margin-top: 1rem;">
                                    Dodaj pierwszą domenę
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Modal zmiany hasła -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">
                    <i class="fa-solid fa-key"></i>
                    Zmień hasło
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Obecne hasło</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password" class="form-label">Nowe hasło</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Potwierdź nowe hasło</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i>
                        Zmień hasło
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal dodawania domeny -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-labelledby="addDomainModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDomainModalLabel">
                    <i class="fa-solid fa-plus"></i>
                    Dodaj domenę
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="domain" class="form-label">URL sitemapy</label>
                        <input type="url" class="form-control" id="domain" name="domain" 
                               placeholder="https://example.com/sitemap.xml" required>
                        <small style="color: var(--text-dark); margin-top: 0.5rem; display: block;">
                            Wprowadź pełny adres URL do sitemapy XML
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Dodaj domenę
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>