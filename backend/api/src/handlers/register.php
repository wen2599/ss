<?php
// Included from /api/index.php

// This script handles user registration.

// --- Input Validation ---
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    jsonError(400, 'Username and password are required.');
}

$username = trim($input['username']);
$password = $input['password'];

if (empty($username) || empty($password)) {
    jsonError(400, 'Username and password cannot be empty.');
}

// Basic username validation (e.g., length, characters)
if (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    jsonError(400, 'Invalid username. Must be 3-30 characters and contain only letters, numbers, and underscores.');
}

// Basic password validation (e.g., length)
if (strlen($password) < 8) {
    jsonError(400, 'Password must be at least 8 characters long.');
}

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonError(409, 'Username already exists.');
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert the new user
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$username, $hashedPassword]);

    // --- Success Response ---
    jsonResponse(201, ['status' => 'success', 'message' => 'User registered successfully.']);

} catch (PDOException $e) {
    error_log("Registration DB Error: " . $e->getMessage());
    jsonError(500, 'Database error during registration.');
} catch (Throwable $e) {
    error_log("Registration Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred during registration.');
}
