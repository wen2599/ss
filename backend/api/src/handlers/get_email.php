<?php
// Included from /api/index.php

// This script retrieves a single email by its ID.

// --- Session Check for Authentication ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAuthenticated = isset($_SESSION['user_id']);

// --- Input Validation ---
if (!isset($_GET['id'])) {
    jsonError(400, 'Email ID is required.');
}

$emailId = (int)$_GET['id'];

if ($emailId <= 0) {
    jsonError(400, 'Invalid Email ID.');
}

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    // Fetch the email
    $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ?");
    $stmt->execute([$emailId]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Authorization Check ---
    if (!$email) {
        jsonError(404, 'Email not found.');
    }

    // If the email is private, only authenticated users can see it.
    if ($email['is_private'] && !$isAuthenticated) {
        jsonError(403, 'Forbidden: You do not have permission to view this email.');
    }

    // --- Success Response ---
    jsonResponse(200, [
        'status' => 'success',
        'data' => $email
    ]);

} catch (PDOException $e) {
    error_log("Get-Email DB Error: " . $e->getMessage());
    jsonError(500, 'Database error while retrieving the email.');
} catch (Throwable $e) {
    error_log("Get-Email Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred.');
}
