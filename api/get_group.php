<?php

header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';

session_start();
requireLogin();

if (!isset($_GET['group_id'])) {
    echo json_encode(['success' => false, 'message' => 'Group ID required']);
    exit();
}

$groupId = $_GET['group_id'];
$userId = $_SESSION['user_id'];

// Check membership
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$groupId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get group info
$stmt = $pdo->prepare("SELECT id, group_name, created_by FROM u_groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

// Get members
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email
    FROM group_members gm
    JOIN users u ON gm.user_id = u.id
    WHERE gm.group_id = ?
");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'group' => $group,
    'members' => $members
]);

?>