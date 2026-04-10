<?php

header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';

session_start();
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['group_name']) || empty(trim($data['group_name']))) {
    echo json_encode(['success' => false, 'message' => 'Group name is required']);
    exit();
}

$userId = $_SESSION['user_id'];
$groupName = trim($data['group_name']);

try {
    $pdo->beginTransaction();
    
    // Create group
    $stmt = $pdo->prepare("INSERT INTO u_groups (group_name, created_by) VALUES (?, ?)");
    $stmt->execute([$groupName, $userId]);
    $groupId = $pdo->lastInsertId();
    
    // Add creator as member
    $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->execute([$groupId, $userId]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Group created', 'group_id' => $groupId]);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to create group: ' . $e->getMessage()]);
}

?>