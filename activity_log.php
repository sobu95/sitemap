<?php
// Włącz wyświetlanie błędów (umieść na górze pliku)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// activity_log.php — Wyświetlanie aktywności użytkowników (tylko dla admina)
require 'auth.php';
require 'db.php';

// Sprawdzamy, czy użytkownik jest administratorem
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

// Ustawienia paginacji
$limit = 20; // Liczba logów na stronie
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filtracja według użytkownika
$user_id_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$user_id_condition = $user_id_filter ? "AND activity_log.user_id = ?" : '';

// Pobieranie dostępnych użytkowników do listy rozwijanej
$user_stmt = $conn->prepare("SELECT id, username FROM users");
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$users = $user_result->fetch_all(MYSQLI_ASSOC);

// Pobieranie logów aktywności z paginacją i filtrowaniem
$query = "SELECT activity_log.activity, activity_log.created_at, users.username 
          FROM activity_log 
          JOIN users ON activity_log.user_id = users.id 
          WHERE 1=1 $user_id_condition 
          ORDER BY activity_log.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($user_id_filter) {
    $stmt->bind_param('iii', $user_id_filter, $limit, $offset);
} else {
    $stmt->bind_param('ii', $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);

// Obliczanie liczby stron
$count_query = "SELECT COUNT(*) AS total 
                FROM activity_log 
                WHERE 1=1 $user_id_condition";
$count_stmt = $conn->prepare($count_query);
if ($user_id_filter) {
    $count_stmt->bind_param('i', $user_id_filter);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Logi aktywności'; include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Logi aktywności użytkowników</h1>

    <!-- Formularz filtrowania -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-6">
            <form method="GET" action="">
                <div class="mb-3">
                    <label for="user_id" class="form-label">Filtruj według użytkownika</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="0">Wszyscy użytkownicy</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_id_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtruj</button>
            </form>
        </div>
    </div>

    <!-- Lista aktywności -->
    <div class="row justify-content-center">
        <div class="col-md-10">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Użytkownik</th>
                        <th>Aktywność</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['username']) ?></td>
                                <td><?= htmlspecialchars($log['activity']) ?></td>
                                <td><?= htmlspecialchars($log['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">Brak logów</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginacja -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&user_id=<?= $user_id_filter ?>">Poprzednia</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&user_id=<?= $user_id_filter ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?>&user_id=<?= $user_id_filter ?>">Następna</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Powrót do panelu admina -->
    <div class="row justify-content-center mt-3">
        <div class="col-md-10 text-center">
            <a href="admin.php" class="btn btn-secondary">Powrót do panelu administratora</a>
        </div>
    </div>
</div>

</body>
</html>
