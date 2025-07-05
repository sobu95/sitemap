<?php
// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Funkcja do tworzenia pliku db.php
function createDbConfigFile($host, $username, $password, $database) {
    $configContent = "<?php\n";
    $configContent .= "// db.php — Połączenie z bazą danych\n";
    $configContent .= "\$host = '$host';\n";
    $configContent .= "\$username = '$username';\n";
    $configContent .= "\$password = '$password';\n";
    $configContent .= "\$database = '$database';\n";
    $configContent .= "\n";
    $configContent .= "\$conn = new mysqli(\$host, \$username, \$password, \$database);\n";
    $configContent .= "\n";
    $configContent .= "// Sprawdzamy, czy połączenie się udało\n";
    $configContent .= "if (\$conn->connect_error) {\n";
    $configContent .= "    die('Błąd połączenia: ' . \$conn->connect_error);\n";
    $configContent .= "}\n";
    $configContent .= "?>";

    // Zapisanie konfiguracji do pliku db.php
    file_put_contents('db.php', $configContent);
}

// Sprawdzamy, czy formularz został wysłany
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dane przesłane przez formularz
    $host = $_POST['db_host'];
    $username = $_POST['db_user'];
    $password = $_POST['db_pass'];
    $database = $_POST['db_name'];
    $admin_user = $_POST['admin_user'];
    $admin_pass = $_POST['admin_pass'];
    $admin_email = $_POST['admin_email'];

    // Próba połączenia z bazą danych
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        die("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }

    // Tworzenie tabeli użytkowników
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    // Tworzenie tabeli resetów haseł
    $sql_password_resets = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(100) NOT NULL,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // Tworzenie tabeli prób logowań
    $sql_login_attempts = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    // Tworzenie tabeli domen
    $sql_domains = "CREATE TABLE IF NOT EXISTS domains (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        domain VARCHAR(255) NOT NULL,
        competitor TINYINT(1) DEFAULT 0, -- 0 dla własnych domen, 1 dla konkurencji
        check_interval_days INT DEFAULT NULL,
        alert_threshold_percent INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // Tworzenie tabeli historii sprawdzeń domen
    $sql_domain_checks = "CREATE TABLE IF NOT EXISTS domain_checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_id INT NOT NULL,
        checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        result TEXT,
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
    )";

    // Tworzenie tabeli logów aktywności
    $sql_activity_log = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        activity VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    // Tworzenie tabeli zmian w sitemapach
    $sql_sitemap_changes = "CREATE TABLE IF NOT EXISTS sitemap_changes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_id INT NOT NULL,
        sitemap TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
    )";


    // Tworzenie tabeli ustawień systemu
    $sql_settings = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        check_interval_days INT DEFAULT 7, -- liczba dni między sprawdzeniami domeny
        alert_threshold_percent INT DEFAULT 10 -- próg procentowy alertu
    )";

    // Tworzenie tabel
    if (
        $conn->query($sql_users) === TRUE &&
        $conn->query($sql_password_resets) === TRUE &&
        $conn->query($sql_login_attempts) === TRUE &&
        $conn->query($sql_domains) === TRUE &&
        $conn->query($sql_domain_checks) === TRUE &&
        $conn->query($sql_activity_log) === TRUE &&
        $conn->query($sql_sitemap_changes) === TRUE &&
        $conn->query($sql_settings) === TRUE
    ) {
        echo "Tabele zostały utworzone pomyślnie.<br>";
        
        // Tworzenie pliku db.php
        createDbConfigFile($host, $username, $password, $database);
        echo "Plik db.php został utworzony pomyślnie.<br>";
    } else {
        echo "Błąd tworzenia tabel: " . $conn->error . "<br>";
    }

    // Dodanie konta administratora
    $admin_pass_hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
    $sql_insert_admin = "INSERT INTO users (username, password, email, role) VALUES ('$admin_user', '$admin_pass_hashed', '$admin_email', 'admin')";

    if ($conn->query($sql_insert_admin) === TRUE) {
        echo "Konto administratora zostało utworzone pomyślnie.<br>";
        echo "Możesz teraz usunąć plik install.php z serwera.<br>";
    } else {
        echo "Błąd tworzenia konta administratora: " . $conn->error . "<br>";
    }

    // Dodanie domyślnych ustawień systemowych
    $sql_insert_settings = "INSERT INTO settings (check_interval_days, alert_threshold_percent) VALUES (7, 10)";
    $conn->query($sql_insert_settings);

    // Zamknięcie połączenia
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pl">
<?php $page_title = 'Instalacja systemu'; include 'inc/head.php'; ?>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Instalacja systemu zarządzania domenami</h1>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="db_host" class="form-label">Host bazy danych</label>
                    <input type="text" class="form-control" id="db_host" name="db_host" required>
                </div>
                <div class="mb-3">
                    <label for="db_user" class="form-label">Użytkownik bazy danych</label>
                    <input type="text" class="form-control" id="db_user" name="db_user" required>
                </div>
                <div class="mb-3">
                    <label for="db_pass" class="form-label">Hasło bazy danych</label>
                    <input type="password" class="form-control" id="db_pass" name="db_pass" required>
                </div>
                <div class="mb-3">
                    <label for="db_name" class="form-label">Nazwa bazy danych</label>
                    <input type="text" class="form-control" id="db_name" name="db_name" required>
                </div>
                <hr>
                <div class="mb-3">
                    <label for="admin_user" class="form-label">Nazwa użytkownika administratora</label>
                    <input type="text" class="form-control" id="admin_user" name="admin_user" required>
                </div>
                <div class="mb-3">
                    <label for="admin_pass" class="form-label">Hasło administratora</label>
                    <input type="password" class="form-control" id="admin_pass" name="admin_pass" required>
                </div>
                <div class="mb-3">
                    <label for="admin_email" class="form-label">E-mail administratora</label>
                    <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Zainstaluj system</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
