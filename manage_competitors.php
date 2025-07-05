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
$domain_id = isset($_GET['domain_id']) ? intval($_GET['domain_id']) : 0;
$user_id = $_SESSION['user_id'];

// Sprawdzamy, czy domena należy do zalogowanego użytkownika
$stmt = $conn->prepare("SELECT domain FROM domains WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $domain_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Nie znaleziono domeny lub brak uprawnień.');
}

$domain = $result->fetch_assoc();
$domain_url = $domain['domain'];
$success_message = '';
$error_message = '';

// Dodawanie konkurencyjnej domeny do wybranej domeny
if (isset($_POST['add_competitor']) && isset($_POST['competitor_domain'])) {
    $competitor_domain = $_POST['competitor_domain'];

    if (!filter_var($competitor_domain, FILTER_VALIDATE_URL)) {
        $error_message = 'Nieprawidłowy URL domeny konkurencyjnej!';
    } else {
        // Sprawdzenie, czy konkurencyjna domena istnieje w bazie
        $stmt = $conn->prepare("SELECT id FROM domains WHERE domain = ? AND competitor = 1");
        $stmt->bind_param('s', $competitor_domain);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            // Dodanie nowej domeny konkurencyjnej do bazy danych
            $stmt = $conn->prepare("INSERT INTO domains (user_id, domain, competitor) VALUES (?, ?, 1)");
            $stmt->bind_param('is', $user_id, $competitor_domain);
            if ($stmt->execute()) {
                $competitor_id = $stmt->insert_id;
            } else {
                $error_message = 'Wystąpił błąd podczas dodawania domeny konkurencyjnej.';
            }
        } else {
            // Pobranie istniejącego ID domeny konkurencyjnej
            $stmt->bind_result($competitor_id);
            $stmt->fetch();
        }

        // Sprawdzenie, czy domena konkurencyjna jest już powiązana z domeną główną
        $stmt = $conn->prepare("SELECT id FROM domain_competitors WHERE domain_id = ? AND competitor_domain_id = ?");
        $stmt->bind_param('ii', $domain_id, $competitor_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_message = 'Ta konkurencyjna domena jest już powiązana z wybraną domeną.';
        } else {
            // Powiązanie domeny konkurencyjnej z domeną główną
            $stmt = $conn->prepare("INSERT INTO domain_competitors (domain_id, competitor_domain_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $domain_id, $competitor_id);
            if ($stmt->execute()) {
                $success_message = 'Domena konkurencyjna została pomyślnie dodana!';
            } else {
                $error_message = 'Wystąpił błąd podczas dodawania powiązania domeny konkurencyjnej.';
            }
        }
    }
}

// Usuwanie powiązania konkurencyjnej domeny
if (isset($_POST['remove_competitor']) && isset($_POST['competitor_id'])) {
    $competitor_id = intval($_POST['competitor_id']);

    // Rozpoczęcie transakcji
    $conn->begin_transaction();

    try {
        // Usunięcie powiązania domeny konkurencyjnej z domeną główną w tabeli domain_competitors
        $stmt = $conn->prepare("DELETE FROM domain_competitors WHERE domain_id = ? AND competitor_domain_id = ?");
        $stmt->bind_param('ii', $domain_id, $competitor_id);
        if (!$stmt->execute()) {
            throw new Exception('Błąd podczas usuwania powiązania domeny konkurencyjnej.');
        }

        // Sprawdzenie, czy domena jest oznaczona jako konkurencyjna (competitor = 1)
        $stmt = $conn->prepare("SELECT competitor FROM domains WHERE id = ? AND competitor = 1");
        $stmt->bind_param('i', $competitor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Jeśli domena jest konkurencyjna, usuń ją z tabeli domains
            $stmt = $conn->prepare("DELETE FROM domains WHERE id = ? AND competitor = 1");
            $stmt->bind_param('i', $competitor_id);
            if (!$stmt->execute()) {
                throw new Exception('Błąd podczas usuwania domeny konkurencyjnej z bazy danych.');
            }
        }

        // Jeśli wszystkie operacje zakończyły się sukcesem, zatwierdzamy transakcję
        $conn->commit();
        $success_message = 'Domena konkurencyjna została usunięta.';
    } catch (Exception $e) {
        // W przypadku błędu cofamy transakcję
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Pobieranie konkurencyjnych domen powiązanych z daną domeną główną
$query = "
    SELECT d.id, d.domain 
    FROM domains d
    JOIN domain_competitors dc ON dc.competitor_domain_id = d.id
    WHERE dc.domain_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $domain_id);
$stmt->execute();
$competitor_result = $stmt->get_result();
$competitors = $competitor_result->fetch_all(MYSQLI_ASSOC);

// Funkcja do paginacji
function getPaginationData($conn, $competitor_id, $page, $items_per_page) {
    $offset = ($page - 1) * $items_per_page;
    
    // Pobieramy 5 ostatnich wyników z domain_checks
    $stmt = $conn->prepare("SELECT result, checked_at FROM domain_checks WHERE domain_id = ? ORDER BY checked_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('iii', $competitor_id, $items_per_page, $offset);
    $stmt->execute();
    $checks_result = $stmt->get_result();
    $checks = $checks_result->fetch_all(MYSQLI_ASSOC);
    
    // Pobieramy liczbę wszystkich wyników do paginacji
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM domain_checks WHERE domain_id = ?");
    $stmt->bind_param('i', $competitor_id);
    $stmt->execute();
    $total_result = $stmt->get_result()->fetch_assoc();
    $total_items = $total_result['total'];
    $total_pages = ceil($total_items / $items_per_page);
    
    return ['checks' => $checks, 'total_pages' => $total_pages];
}


// Usuwanie powiązania konkurencyjnej domeny
if (isset($_POST['remove_competitor']) && isset($_POST['competitor_id'])) {
    $competitor_id = intval($_POST['competitor_id']);

    // Usunięcie powiązania konkurencyjnej domeny z domeną główną
    $stmt = $conn->prepare("DELETE FROM domain_competitors WHERE domain_id = ? AND competitor_domain_id = ?");
    $stmt->bind_param('ii', $domain_id, $competitor_id);

    if ($stmt->execute()) {
        $success_message = 'Domena konkurencyjna została usunięta.';
    } else {
        $error_message = 'Wystąpił błąd podczas usuwania domeny konkurencyjnej.';
    }
}

?>
<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Zarządzanie konkurencyjnymi domenami - ' . htmlspecialchars($domain['domain']); include 'inc/head.php'; ?>
<body>
     <?php
    include('inc/sidebar.php');
    ?>

   
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
<div class="container mt-5">
    <h1 class="text-center mb-4">Zarządzanie konkurencją: <?= htmlspecialchars($domain['domain']) ?></h1>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Formularz dodawania konkurencyjnej domeny -->
    <h3>Dodaj konkurencyjną domenę:</h3>
    <form method="POST" action="">
        <div class="mb-3">
            <label for="competitor_domain" class="form-label">URL konkurencyjnej sitemapy</label>
            <input type="text" class="form-control" id="competitor_domain" name="competitor_domain" placeholder="https://example.com/sitemap.xml" required>
        </div>
        <button type="submit" name="add_competitor" class="btn btn-primary">Dodaj konkurencję</button>
    </form>

    <!-- Lista konkurencyjnych domen -->
    <h3 class="mt-5">Lista konkurencyjnych domen:</h3>
    <?php if (count($competitors) > 0): ?>
        <?php foreach ($competitors as $competitor): ?>
            <?php
                // Obsługa paginacji dla każdej domeny konkurencyjnej
                $page = isset($_GET['page_' . $competitor['id']]) ? intval($_GET['page_' . $competitor['id']]) : 1;
                $items_per_page = 5;
                $pagination_data = getPaginationData($conn, $competitor['id'], $page, $items_per_page);
                $checks = $pagination_data['checks'];
                $total_pages = $pagination_data['total_pages'];
            ?>
            <div class="border p-3 mb-3">
                <h4>Domena konkurencyjna: <?= htmlspecialchars($competitor['domain']) ?></h4>
                 <!-- Formularz do usunięcia domeny konkurencyjnej -->
                    <form method="POST" action="" onsubmit="return confirm('Czy na pewno chcesz usunąć tę domenę konkurencyjną?');">
                        <input type="hidden" name="competitor_id" value="<?= $competitor['id'] ?>">
                        <button type="submit" name="remove_competitor" class="btn btn-danger btn-sm">Usuń</button>
                    </form>
                <p>Ostatnie sprawdzenia sitemapy:</p>
                <?php if (count($checks) > 0): ?>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Data sprawdzenia</th>
                                <th>Liczba podstron (URL)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checks as $check): ?>
                                <tr>
                                    <td><?= htmlspecialchars($check['checked_at']) ?></td>
                                    <td><?= htmlspecialchars($check['result']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Paginacja -->
                    <nav aria-label="Paginacja">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page_<?= $competitor['id'] ?>=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>

                <?php else: ?>
                    <p>Brak sprawdzeń dla tej domeny.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Brak dodanych konkurencyjnych domen.</p>
    <?php endif; ?>

    <!-- Powrót do szczegółów domeny -->
    <div class="text-center mt-4">
        <a href="domain.php?id=<?= $domain_id ?>" class="btn btn-secondary">Powrót do szczegółów domeny</a>
    </div>
    <br />
</div>
</main>
</body>
</html>
