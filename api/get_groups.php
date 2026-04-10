<?php

header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';

session_start();
requireLogin();

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT g.id, g.group_name, g.created_at,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
    FROM u_groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$userId]);
$groups = $stmt->fetchAll();

echo json_encode(['success' => true, 'groups' => $groups]);

?>