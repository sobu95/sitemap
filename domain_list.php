<?php
// Włącz wyświetlanie błędów (umieść na górze pliku)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// domain_list.php — Lista domen
session_start();
require 'auth.php';
require 'db.php';

// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sprawdzanie, czy użytkownik jest administratorem
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

// Ustawienia paginacji
$items_per_page = 15;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Filtracja
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Pobieranie użytkowników do filtracji
$user_stmt = $conn->prepare("SELECT id, username FROM users");
$user_stmt->execute();
$users = $user_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pobieranie listy domen z paginacją i filtracją
$query = "
    SELECT domains.id, domains.domain, domains.created_at, users.username,
           dc.result AS last_check_result,
           dc.checked_at AS last_check_date
    FROM domains
    JOIN users ON domains.user_id = users.id
    LEFT JOIN (
        SELECT domain_id, result, checked_at,
               ROW_NUMBER() OVER (PARTITION BY domain_id ORDER BY checked_at DESC) as rn
        FROM domain_checks
    ) dc ON domains.id = dc.domain_id AND dc.rn = 1
    WHERE (0 = ? OR domains.user_id = ?)
    ORDER BY domains.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('iiii', $selected_user_id, $selected_user_id, $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$domains = $result->fetch_all(MYSQLI_ASSOC);

// Pobieranie liczby wszystkich domen (do paginacji)
$count_query = "
    SELECT COUNT(*) AS total
    FROM domains
    WHERE (0 = ? OR domains.user_id = ?)
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param('ii', $selected_user_id, $selected_user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_count / $items_per_page);
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Lista domen'; include 'inc/head.php'; ?>
    <style>
        .filter-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .filter-container select,
        .filter-container button {
            margin-left: 10px;
        }
    </style>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Lista domen</h1>

    <!-- Formularz filtracji -->
    <div class="row mb-4">
        <div class="col-md-10">
            <div class="filter-container">
                <form method="GET" action="domain_list.php" class="d-flex">
                    <select name="user_id" class="form-select" style="width: auto;">
                        <option value="0">Wszyscy użytkownicy</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= htmlspecialchars($user['id']) ?>" <?= $selected_user_id == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtruj</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabelka z listą domen -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-10">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Domena</th>
                        <th>Data dodania</th>
                        <th>Dodana przez</th>
                        <th>Liczba URL w sitemapie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($domains)): ?>
                        <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td>
                                    <?php
                                    $processed_domain = parse_url($domain['domain'], PHP_URL_HOST);
                                    $processed_domain = preg_replace('/^www\./', '', $processed_domain);
                                    $processed_domain = preg_replace('/\/sitemap\.xml$/', '', $processed_domain);
                                    echo htmlspecialchars($processed_domain);
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($domain['created_at']) ?></td>
                                <td><?= htmlspecialchars($domain['username']) ?></td>
                                <td>
                                    <?php
                                    if (!empty($domain['last_check_result'])) {
                                        echo htmlspecialchars($domain['last_check_result']);
                                    } else {
                                        echo 'Brak danych';
                                    }
                                    echo '<br>Ostatnie sprawdzenie: ' . (!empty($domain['last_check_date']) ? htmlspecialchars($domain['last_check_date']) : 'Brak danych');
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">Brak domen do wyświetlenia</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginacja -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max($page - 1, 1) . ($selected_user_id ? '&user_id=' . $selected_user_id : '') ?>">Poprzednia</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i . ($selected_user_id ? '&user_id=' . $selected_user_id : '') ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($page + 1, $total_pages) . ($selected_user_id ? '&user_id=' . $selected_user_id : '') ?>">Następna</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Powrót do panelu administratora -->
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <a href="admin.php" class="btn btn-secondary">Powrót do panelu administratora</a>
        </div>
    </div>
</div>

</body>
</html>