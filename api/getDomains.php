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

$user_id = $_SESSION['user_id'];

$sql = "SELECT d.id, d.domain, d.created_at,
               (SELECT result FROM domain_checks WHERE domain_id = d.id ORDER BY checked_at DESC LIMIT 1) AS last_check,
               (SELECT result FROM domain_checks WHERE domain_id = d.id ORDER BY checked_at DESC LIMIT 1 OFFSET 1) AS previous_check
        FROM domains d
        WHERE d.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$domains = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['domains' => $domains]);


