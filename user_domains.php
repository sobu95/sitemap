<?php
// user_domains.php — Przegląd domen użytkownika dla administratora
require 'auth.php';
require 'db.php';

// Sprawdzamy, czy użytkownik jest administratorem
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

// Pobieramy ID użytkownika
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Pobieramy informacje o użytkowniku
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Użytkownik nie został znaleziony.";
    exit();
}

$user = $result->fetch_assoc();

// Pobieramy listę domen użytkownika
$stmt = $conn->prepare("SELECT domain, created_at FROM domains WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$domains = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Domeny użytkownika - ' . htmlspecialchars($user['username']); include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Domeny użytkownika: <?= htmlspecialchars($user['username']) ?></h1>

    <!-- Lista domen -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if (count($domains) > 0): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Domena</th>
                            <th>Data dodania</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $domain): ?>
                            <tr>
                                <td><?= htmlspecialchars($domain['domain']) ?></td>
                                <td><?= htmlspecialchars($domain['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">Ten użytkownik nie dodał jeszcze żadnych domen.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Powrót do panelu admina -->
    <div class="row justify-content-center mt-3">
        <div class="col-md-8 text-center">
            <a href="admin.php" class="btn btn-secondary">Powrót do panelu administratora</a>
        </div>
    </div>
</div>

</body>
</html>
