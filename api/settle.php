<?php

header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';

session_start();
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['group_id'], $data['to_user_id'], $data['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$groupId = $data['group_id'];
$toUserId = intval($data['to_user_id']);
$amount = floatval($data['amount']);
$fromUserId = $_SESSION['user_id'];

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit();
}

if ($fromUserId == $toUserId) {
    echo json_encode(['success' => false, 'message' => 'Cannot settle with yourself']);
    exit();
}

// Check if both users are members of the group
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM group_members 
    WHERE group_id = ? AND user_id IN (?, ?)
");
$stmt->execute([$groupId, $fromUserId, $toUserId]);
$result = $stmt->fetch();

if ($result['count'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Both users must be members of the group']);
    exit();
}

// Insert payment
$stmt = $pdo->prepare("INSERT INTO payments (group_id, from_user_id, to_user_id, amount) VALUES (?, ?, ?, ?)");
$result = $stmt->execute([$groupId, $fromUserId, $toUserId, $amount]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
}

?>