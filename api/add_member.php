<?php

header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';

session_start();
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['group_id'], $data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Group ID and email required']);
    exit();
}

$groupId = $data['group_id'];
$email = $data['email'];
$userId = $_SESSION['user_id'];

// Check if user is group member
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$groupId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You are not a member of this group']);
    exit();
}

// Find user by email
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
$stmt->execute([$email]);
$userToAdd = $stmt->fetch();

if (!$userToAdd) {
    echo json_encode(['success' => false, 'message' => 'User not found with this email']);
    exit();
}

// Check if already member
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$groupId, $userToAdd['id']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'User is already a member']);
    exit();
}

// Add member
$stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
$result = $stmt->execute([$groupId, $userToAdd['id']]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Member added successfully', 'member' => $userToAdd]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add member']);
}

?>