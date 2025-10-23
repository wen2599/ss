<?php
// Included from /api/index.php

// This script handles user login.

// --- Session Configuration ---
// It is crucial to start the session to maintain login state.
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie parameters for security
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        // 'domain' => '.yourdomain.com', // Set your domain
        'samesite' => 'Lax' // Or 'Strict', depending on your needs
        // 'secure' => true, // Only send cookie over HTTPS
        // 'httponly' => true, // Prevent JavaScript access to the session cookie
    ]);
    session_start();
}

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

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    // Find the user by username
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verify password
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError(401, 'Invalid username or password.');
    }

    // --- Session Regeneration & Login ---
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // Store user information in the session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    // --- Success Response ---
    jsonResponse(200, [
        'status' => 'success',
        'message' => 'Login successful.',
        'data' => [
            'username' => $user['username']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Login DB Error: " . $e->getMessage());
    jsonError(500, 'Database error during login.');
} catch (Throwable $e) {
    error_log("Login Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred during login.');
}
