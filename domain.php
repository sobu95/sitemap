<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'auth.php';
require 'db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!is_logged_in()) {
    header('Location: index.php');
    exit();
}

// Pobieramy ID domeny z parametru URL
$domain_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

// Sprawdzamy, czy domena należy do zalogowanego użytkownika
$stmt = $conn->prepare("SELECT domain, competitor, check_interval_days, alert_threshold_percent FROM domains WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $domain_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<!DOCTYPE html>
<html lang="pl">';
    $page_title = "Błąd";
    ob_start();
    include "inc/head.php";
    $head = ob_get_clean();
    echo $head;
    echo '<body>
        <div class="auth-container">
            <div class="auth-card">
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Domena nie została znaleziona lub nie masz do niej dostępu.
                </div>
                <a href="dashboard.php" class="btn btn-secondary" style="width: 100%;">
                    <i class="fa-solid fa-arrow-left"></i>
                    Powrót do panelu
                </a>
            </div>
        </div>
</body>
</html>';
    exit();
}

$domain = $result->fetch_assoc();
$domain_url = $domain['domain'];
$is_competitor = $domain['competitor'];
$current_interval = $domain['check_interval_days'];
$current_threshold = $domain['alert_threshold_percent'];

$stmt = $conn->prepare("SELECT check_interval_days, alert_threshold_percent FROM settings LIMIT 1");
$stmt->execute();
$global_settings = $stmt->get_result()->fetch_assoc();
$current_interval = $current_interval ?? $global_settings['check_interval_days'];
$current_threshold = $current_threshold ?? $global_settings['alert_threshold_percent'];

$success_message = '';
$error_message = '';

// Aktualizacja ustawień domeny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_domain_settings'])) {
    $new_interval = intval($_POST['check_interval_days']);
    $new_threshold = intval($_POST['alert_threshold_percent']);
    $stmt = $conn->prepare("UPDATE domains SET check_interval_days = ?, alert_threshold_percent = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('iiii', $new_interval, $new_threshold, $domain_id, $user_id);
    if ($stmt->execute()) {
        $success_message = 'Ustawienia domeny zostały zaktualizowane.';
        $current_interval = $new_interval;
        $current_threshold = $new_threshold;
        log_activity($user_id, "Zaktualizował ustawienia domeny: $domain_url");
    } else {
        $error_message = 'Wystąpił błąd podczas aktualizacji ustawień.';
    }
}

// Paginacja dla historii sprawdzeń
$items_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Pobieranie historii sprawdzeń z paginacją
$stmt = $conn->prepare("SELECT SQL_CALC_FOUND_ROWS checked_at, result FROM domain_checks WHERE domain_id = ? ORDER BY checked_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param('iii', $domain_id, $items_per_page, $offset);
$stmt->execute();
$history_result = $stmt->get_result();
$history = $history_result->fetch_all(MYSQLI_ASSOC);

// Liczba wszystkich wyników (do paginacji)
$total_items_result = $conn->query("SELECT FOUND_ROWS() AS total_items");
$total_items = $total_items_result->fetch_assoc()['total_items'];
$total_pages = ceil($total_items / $items_per_page);

// Pobieranie konkurencyjnych domen
$stmt = $conn->prepare("SELECT id, domain FROM domains WHERE user_id = ? AND competitor = 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$competitor_result = $stmt->get_result();
$competitors = $competitor_result->fetch_all(MYSQLI_ASSOC);

function simplifyDomain($url) {
    $parsed_url = parse_url($url);
    $domain = isset($parsed_url['host']) ? $parsed_url['host'] : $parsed_url['path'];
    return preg_replace('/^www\./', '', $domain);
}
?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Ankor-PukSoft - Szczegóły domeny'; include 'inc/head.php'; ?>
<body>

<div class="container">
    <?php include('inc/sidebar.php'); ?>

    <main class="main-content">
        <div class="breadcrumb">
            <a href="dashboard.php">Domeny</a>
            <span class="breadcrumb-separator">/</span>
            <span><?= htmlspecialchars(simplifyDomain($domain['domain'])) ?></span>
        </div>

        <header class="header">
            <h1>Szczegóły domeny</h1>
            <div class="header-actions">
                <a href="check_sitemap.php?domain_id=<?= $domain_id ?>" class="btn btn-primary">
                    <i class="fa-solid fa-play"></i>
                    Sprawdź teraz
                </a>
            </div>
        </header>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Informacje o domenie -->
        <div class="content-panel">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-globe"></i>
                <?= htmlspecialchars(simplifyDomain($domain['domain'])) ?>
            </h3>
            <p style="color: var(--text-dark); margin-bottom: 1.5rem;">
                <strong>Pełny URL:</strong> <?= htmlspecialchars($domain['domain']) ?>
            </p>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="check_sitemap.php?domain_id=<?= $domain_id ?>" class="btn btn-primary">
                    <i class="fa-solid fa-play"></i>
                    Sprawdź sitemapę
                </a>
                <a href="compare_sitemaps.php?domain_id=<?= $domain_id ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-code-compare"></i>
                    Porównaj sitemapy
                </a>
                <a href="manage_competitors.php?domain_id=<?= $domain_id ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-users"></i>
                    Zarządzaj konkurencją
                </a>
                <a href="show_latest_sitemap.php?domain_id=<?= $domain_id ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-file-code"></i>
                    Pokaż ostatnią sitemapę
                </a>
            </div>
        </div>

        <!-- Ustawienia domeny -->
        <div class="content-panel">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-cog"></i>
                Ustawienia domeny
            </h3>
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="check_interval_days" class="form-label">Częstość sprawdzania sitemapy (dni)</label>
                        <input type="number" class="form-control" id="check_interval_days" name="check_interval_days" value="<?= htmlspecialchars($current_interval) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="alert_threshold_percent" class="form-label">Próg procentowej zmiany</label>
                        <input type="number" class="form-control" id="alert_threshold_percent" name="alert_threshold_percent" value="<?= htmlspecialchars($current_threshold) ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_domain_settings" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i>
                    Zapisz ustawienia
                </button>
            </form>
        </div>

        <!-- Wybór konkretnej sitemapy -->
        <div class="content-panel">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-file-code"></i>
                Pokaż konkretną sitemapę
            </h3>
            <?php
            $uploads_dir = 'sitemaps/' . $user_id;
            $sitemap_files = glob($uploads_dir . '/' . $domain_id . '_*.xml');

            if (!empty($sitemap_files)):
                usort($sitemap_files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
            ?>
                <form action="show_selected_sitemap.php" method="GET">
                    <input type="hidden" name="domain_id" value="<?= $domain_id ?>">
                    <div class="form-group">
                        <label for="sitemap_file" class="form-label">Wybierz sitemapę:</label>
                        <select class="form-control form-select" id="sitemap_file" name="sitemap_file">
                            <?php foreach ($sitemap_files as $file): ?>
                                <?php
                                $filename = basename($file);
                                $file_time = filemtime($file);
                                $date_str = date('Y-m-d H:i:s', $file_time);
                                ?>
                                <option value="<?= htmlspecialchars($filename) ?>"><?= htmlspecialchars($filename) ?> (<?= $date_str ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <i class="fa-solid fa-eye"></i>
                        Pokaż wybraną sitemapę
                    </button>
                </form>
            <?php else: ?>
                <p style="color: var(--text-dark);">Brak zapisanych sitemap dla tej domeny.</p>
            <?php endif; ?>
        </div>

        <!-- Historia sprawdzeń -->
        <div class="content-panel">
            <h3 style="margin-bottom: 1.5rem;">
                <i class="fa-solid fa-history"></i>
                Historia sprawdzeń
            </h3>
            <?php if (count($history) > 0): ?>
                <table class="domain-table">
                    <thead>
                        <tr>
                            <th>Data sprawdzenia</th>
                            <th>Liczba podstron</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $check): ?>
                            <tr>
                                <td><?= htmlspecialchars($check['checked_at']) ?></td>
                                <td><?= htmlspecialchars($check['result']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginacja -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginacja">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?id=<?= $domain_id ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-dark); padding: 2rem;">
                    <i class="fa-solid fa-clock" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i><br>
                    Brak danych o sprawdzeniach dla tej domeny.
                </p>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>