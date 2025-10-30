<?php
require_once '../db.php';
require_once '../functions.php';

function register() {
    global $pdo;
    $data = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) return json_error('Invalid email');

    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO temp_tokens (email, token) VALUES (?, ?)");
    $stmt->execute([$email, $token]);

    echo json_encode(['message' => 'Send email with token in subject to verify']);
}

function login() {
    global $pdo;
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = generateJWT(['id' => $user['id'], 'email' => $email]);
        echo json_encode(['token' => $token]);
    } else {
        json_error('User not found');
    }
}