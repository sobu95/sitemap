<?php
session_start();
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$domain_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id   = $_SESSION['user_id'];

// Verify access to domain
$stmt = $conn->prepare('SELECT id FROM domains WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $domain_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Domain not found']);
    exit();
}

// Fetch history
$history_stmt = $conn->prepare('SELECT checked_at, result FROM domain_checks WHERE domain_id = ? ORDER BY checked_at DESC');
$history_stmt->bind_param('i', $domain_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history = $history_result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['history' => $history]);

