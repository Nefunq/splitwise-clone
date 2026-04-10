<?php

header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';

session_start();
requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['group_id'], $data['description'], $data['amount'], $data['paid_by'], $data['expense_date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$groupId = $data['group_id'];
$description = $data['description'];
$amount = floatval($data['amount']);
$paidBy = intval($data['paid_by']);
$expenseDate = $data['expense_date'];
$userId = $_SESSION['user_id'];

// Verify user is member of group
$stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->execute([$groupId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You are not a member of this group']);
    exit();
}

// Get all group members
$stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();
$memberCount = count($members);
$memberIds = array_column($members, 'user_id');

if ($memberCount == 0) {
    echo json_encode(['success' => false, 'message' => 'No members in group']);
    exit();
}

// Calculate share per person (equal split)
$sharePerPerson = round($amount / $memberCount, 2);
$totalShares = $sharePerPerson * $memberCount;
$difference = round($amount - $totalShares, 2);

try {
    $pdo->beginTransaction();
    
    // Insert expense
    $stmt = $pdo->prepare("INSERT INTO expenses (group_id, description, amount, paid_by, expense_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$groupId, $description, $amount, $paidBy, $expenseDate]);
    $expenseId = $pdo->lastInsertId();
    
    // Insert splits
    for ($i = 0; $i < $memberCount; $i++) {
        $share = $sharePerPerson;
        // Add difference to last member to ensure exact total
        if ($i == $memberCount - 1) {
            $share += $difference;
        }
        $stmt = $pdo->prepare("INSERT INTO expense_splits (expense_id, user_id, share) VALUES (?, ?, ?)");
        $stmt->execute([$expenseId, $memberIds[$i], $share]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to add expense: ' . $e->getMessage()]);
}

?>