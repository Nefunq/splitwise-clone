<?php

header('Content-Type: application/json');
require_once '../config.php';
require_once '../auth.php';

session_start();
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'], $data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Missing email or password']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
$stmt->execute([$data['email']]);
$user = $stmt->fetch();

if ($user && password_verify($data['password'], $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
}

?>