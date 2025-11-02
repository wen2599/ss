<?php
// backend/handlers/user_login.php

// This file is included by api.php, so $pdo and JWTHandler are available.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (empty($email) || empty($password)) {
    send_json_response(['status' => 'error', 'message' => 'Email and password are required.'], 400);
}

// --- Find user and verify password ---
try {
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Password is correct, generate JWT
        $token = JWTHandler::generate_token($user['id']);
        send_json_response([
            'status' => 'success',
            'message' => 'Login successful.',
            'token' => $token
        ]);
    } else {
        // Invalid credentials
        send_json_response(['status' => 'error', 'message' => 'Invalid email or password.'], 401);
    }

} catch (PDOException $e) {
    send_json_response(['status' => 'error', 'message' => 'Database error during login.'], 500);
}
