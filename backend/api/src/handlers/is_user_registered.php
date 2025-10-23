<?php
// Included from /api/index.php

// This script checks if an email is already registered.

// --- Input Validation ---
if (!isset($_GET['email'])) {
    jsonError(400, 'Email parameter is required.');
}

$email = trim($_GET['email']);

if (empty($email)) {
    jsonError(400, 'Email cannot be empty.');
}

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    // Check if email exists (stored in the username column)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$email]);
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
    jsonError(500, 'Database error while checking email.');
} catch (Throwable $e) {
    error_log("Is-Registered Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred.');
}
