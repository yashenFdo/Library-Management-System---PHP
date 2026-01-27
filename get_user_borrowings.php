<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['count' => 0, 'error' => 'Not authenticated']);
    exit;
}

$userId = intval($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['count' => 0, 'error' => 'Invalid user ID']);
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("SELECT COUNT(*) as count FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['count' => $result['count']]);
?>