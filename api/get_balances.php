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

// Get all members
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email
    FROM group_members gm
    JOIN users u ON gm.user_id = u.id
    WHERE gm.group_id = ?
");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

$balances = [];

foreach ($members as $member) {
    $memberId = $member['id'];
    
    // Total paid by this user
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM expenses WHERE group_id = ? AND paid_by = ?");
    $stmt->execute([$groupId, $memberId]);
    $totalPaid = $stmt->fetch()['total_paid'];
    
    // Total owed by this user (their share of expenses)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(es.share), 0) as total_owed
        FROM expense_splits es
        JOIN expenses e ON es.expense_id = e.id
        WHERE e.group_id = ? AND es.user_id = ?
    ");
    $stmt->execute([$groupId, $memberId]);
    $totalOwed = $stmt->fetch()['total_owed'];
    
    // Payments received (others paid to this user)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as received FROM payments WHERE group_id = ? AND to_user_id = ?");
    $stmt->execute([$groupId, $memberId]);
    $received = $stmt->fetch()['received'];
    
    // Payments made (this user paid to others)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as paid_out FROM payments WHERE group_id = ? AND from_user_id = ?");
    $stmt->execute([$groupId, $memberId]);
    $paidOut = $stmt->fetch()['paid_out'];
    
    // Net balance: positive means user is owed money, negative means they owe
    $netBalance = $totalPaid - $totalOwed + $received - $paidOut;
    
    $balances[] = [
        'user_id' => $memberId,
        'name' => $member['name'],
        'email' => $member['email'],
        'balance' => round($netBalance, 2)
    ];
}

echo json_encode(['success' => true, 'balances' => $balances]);

?>