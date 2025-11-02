<?php
// backend/handlers/user_register.php

// This file is included by api.php, so $pdo is available.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

// --- Validation ---
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(['status' => 'error', 'message' => 'Invalid or empty email.'], 400);
}

if (empty($password) || strlen($password) < 6) {
    send_json_response(['status' => 'error', 'message' => 'Password must be at least 6 characters long.'], 400);
}

// --- Check if user already exists ---
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        send_json_response(['status' => 'error', 'message' => 'Email is already registered.'], 409);
    }
} catch (PDOException $e) {
    send_json_response(['status' => 'error', 'message' => 'Database error during user check.'], 500);
}


// --- Create new user ---
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->execute([$email, $password_hash]);

    send_json_response(['status' => 'success', 'message' => 'User registered successfully.'], 201);

} catch (PDOException $e) {
    send_json_response(['status' => 'error', 'message' => 'Failed to register user.'], 500);
}
