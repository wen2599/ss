<?php
// Included from /api/index.php

// This script handles user registration.

// --- Input Validation ---
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || !isset($input['password'])) {
    jsonError(400, 'Email and password are required.');
}

$email = trim($input['email']);
$password = $input['password'];

if (empty($email) || empty($password)) {
    jsonError(400, 'Email and password cannot be empty.');
}

// Basic email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError(400, 'Invalid email format.');
}

// Basic password validation (e.g., length)
if (strlen($password) < 6) {
    jsonError(400, 'Password must be at least 6 characters long.');
}

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonError(409, 'Email already exists.');
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert the new user
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$email, $hashedPassword]);

    // --- Success Response ---
    jsonResponse(201, ['status' => 'success', 'message' => 'User registered successfully.']);

} catch (PDOException $e) {
    error_log("Registration DB Error: " . $e->getMessage());
    jsonError(500, 'Database error during registration.');
} catch (Throwable $e) {
    error_log("Registration Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred during registration.');
}
