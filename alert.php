<?php
// Włącz wyświetlanie błędów (umieść na górze pliku)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// alert.php — Skrypt do wysyłania alertów e-mail o zmianach w liczbie URL-i w sitemapie
require 'db.php'; // Połączenie z bazą danych
header('Content-Type: text/html; charset=utf-8');

// Funkcja wysyłająca alert e-mail
function sendAlert($user_id, $domain_url, $previous_count, $current_count, $percentage_change) {
    global $conn;

    // Pobieramy dane użytkownika
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    $email = $user['email'];
    $username = $user['username'];

    // Tworzymy temat i treść wiadomości
    $subject = "Alert: Zmiana liczby URL-i w sitemapie dla domeny {$domain_url}";
    $message = "
    {$username},

    Liczba URL-i w sitemapie dla domeny {$domain_url} zmieniła się o {$percentage_change}%.

    Poprzednia liczba URL-i: {$previous_count}
    Obecna liczba URL-i: {$current_count}

    Prosimy o sprawdzenie szczegółów w panelu.";

    // Wysyłamy e-mail (funkcja mail() może być zastąpiona przez np. PHPMailer)
    mail($email, $subject, $message);
}

// Funkcja sprawdzająca, czy liczba URL-i zmieniła się o więcej niż ustawiony próg procentowy
function checkForAlerts() {
    global $conn;

    // Pobieramy ustawienia dotyczące alertów (próg procentowy)
    $stmt = $conn->prepare("SELECT alert_threshold_percent FROM settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    $alert_threshold_percent = $settings['alert_threshold_percent'];

    // Pobieramy wszystkie domeny i ich ostatnie dwa sprawdzenia
    $stmt = $conn->prepare("
        SELECT d.id AS domain_id, d.user_id, d.domain,
               dc1.result AS current_count, dc1.checked_at AS current_checked_at,
               dc2.result AS previous_count, dc2.checked_at AS previous_checked_at
        FROM domains d
        JOIN domain_checks dc1 ON dc1.domain_id = d.id
        LEFT JOIN domain_checks dc2 ON dc2.domain_id = d.id AND dc2.checked_at < dc1.checked_at
        WHERE dc1.checked_at = (
            SELECT MAX(checked_at) FROM domain_checks WHERE domain_id = d.id
        )
        AND dc2.checked_at = (
            SELECT MAX(checked_at) FROM domain_checks WHERE domain_id = d.id AND checked_at < dc1.checked_at
        )
    ");
    $stmt->execute();
    $domains = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Sprawdzamy każdą domenę i obliczamy procentową zmianę
    foreach ($domains as $domain) {
        $previous_count = intval($domain['previous_count']);
        $current_count = intval($domain['current_count']);
        
        if ($previous_count > 0) {
            // Obliczamy procentową zmianę
            $difference = abs($current_count - $previous_count);
            $percentage_change = ($difference / $previous_count) * 100;

            // Jeśli zmiana przekracza próg, wysyłamy alert
            if ($percentage_change >= $alert_threshold_percent) {
                sendAlert(
                    $domain['user_id'],
                    $domain['domain'],
                    $previous_count,
                    $current_count,
                    number_format($percentage_change, 2)
                );
                echo "Wysłano alert dla domeny: {$domain['domain']} (Zmiana: {$percentage_change}%)\n";
            }
        }
    }
}

// Wywołanie funkcji do sprawdzania i wysyłania alertów
checkForAlerts();
