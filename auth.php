<?php
// Włącz wyświetlanie błędów (umieść na górze pliku)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// auth.php — Obsługa logowania, rejestracji, resetu hasła i aktywności
session_start();
require 'db.php'; // Połączenie z bazą danych

// Funkcja logowania
function login($username, $password) {
    global $conn;

    // Sprawdzanie, ile prób logowania było w ostatnich 15 minutach (zabezpieczenie przed brutalnym atakiem)
    if (!check_login_attempts($username)) {
        return false;
    }

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Logowanie się powiodło — zapisujemy sesję użytkownika
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        log_activity($user['id'], "Zalogował się.");
        reset_login_attempts($username); // Resetowanie liczby prób logowania
        return true;
    } else {
        log_login_attempt($username); // Logowanie nieudanej próby
        return false;
    }
}

// Funkcja rejestracji
function register($username, $password, $email) {
    global $conn;

    // Sprawdzanie, czy użytkownik już istnieje
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        // Użytkownik już istnieje
        return false;
    }

    // Szyfrowanie hasła
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Wstawianie nowego użytkownika do bazy danych
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param('sss', $username, $hashed_password, $email);

    if ($stmt->execute()) {
        // Rejestracja powiodła się — logowanie aktywności
        $user_id = $conn->insert_id;
        log_activity($user_id, "Zarejestrował się.");
        return true;
    } else {
        return false;
    }
}

// Funkcja do resetowania hasła
function reset_password($email, $new_password) {
    global $conn;

    // Sprawdzanie, czy użytkownik istnieje
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return false; // Użytkownik nie istnieje
    }

    // Szyfrowanie nowego hasła
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Aktualizacja hasła w bazie danych
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param('ss', $hashed_password, $email);
    
    if ($stmt->execute()) {
        log_activity($result->fetch_assoc()['id'], "Zresetował hasło.");
        return true;
    } else {
        return false;
    }
}

// Funkcja logowania aktywności użytkownika
function log_activity($user_id, $activity) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
    $stmt->bind_param('is', $user_id, $activity);
    $stmt->execute();
}

// Funkcja sprawdzająca, czy użytkownik jest zalogowany
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Funkcja sprawdzająca, czy użytkownik jest administratorem
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Funkcja wylogowania
function logout() {
    log_activity($_SESSION['user_id'], "Wylogował się.");
    session_destroy();
    header("Location: index.php");
    exit();
}

// Logowanie prób nieudanych logowań
function log_login_attempt($username) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO login_attempts (username, attempted_at) VALUES (?, NOW())");
    $stmt->bind_param('s', $username);
    $stmt->execute();
}

// Sprawdzanie, ile prób logowania było w ostatnich 15 minutach (max 5 prób)
function check_login_attempts($username) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS attempt_count FROM login_attempts WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempt_count = $result->fetch_assoc()['attempt_count'];

    return $attempt_count < 5;
}

// Resetowanie liczby prób logowania po udanym logowaniu
function reset_login_attempts($username) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
}
?>
