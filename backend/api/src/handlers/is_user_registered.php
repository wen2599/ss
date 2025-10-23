<?php
// Included from /api/index.php

// This script checks if a username is already registered.

// --- Input Validation ---
if (!isset($_GET['username'])) {
    jsonError(400, 'Username parameter is required.');
}

$username = trim($_GET['username']);

if (empty($username)) {
    jsonError(400, 'Username cannot be empty.');
}

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $isRegistered = ($stmt->fetch() !== false);

    // --- Success Response ---
    jsonResponse(200, [
        'status' => 'success',
        'data' => [
            'isRegistered' => $isRegistered
        ]
    ]);

} catch (PDOException $e) {
    error_log("Is-Registered DB Error: " . $e->getMessage());
    jsonError(500, 'Database error while checking username.');
} catch (Throwable $e) {
    error_log("Is-Registered Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred.');
}
