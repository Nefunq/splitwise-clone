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

$stmt = $pdo->prepare("
    SELECT e.id, e.description, e.amount, e.expense_date, 
           u.name as paid_by_name, e.paid_by
    FROM expenses e
    JOIN users u ON e.paid_by = u.id
    WHERE e.group_id = ?
    ORDER BY e.expense_date DESC, e.created_at DESC
");
$stmt->execute([$groupId]);
$expenses = $stmt->fetchAll();

// Get splits for each expense
foreach ($expenses as &$expense) {
    $stmt = $pdo->prepare("
        SELECT es.share, u.name
        FROM expense_splits es
        JOIN users u ON es.user_id = u.id
        WHERE es.expense_id = ?
    ");
    $stmt->execute([$expense['id']]);
    $expense['splits'] = $stmt->fetchAll();
}

echo json_encode(['success' => true, 'expenses' => $expenses]);

?>