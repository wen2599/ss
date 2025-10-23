<?php
declare(strict_types=1);

// Assumes jsonResponse and jsonError functions are available from index.php
// Assumes getDbConnection() is available from index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonError(401, 'Unauthorized. Please log in.');
}

$pdo = getDbConnection();

// 2. Fetch emails from the database
// This is a simplified version. In a real application, you'd likely want to filter emails
// based on the logged-in user. For example, if the `emails.to` field can be linked to a user.
// For now, we will just list all emails, assuming this is an admin-like feature.
try {
    $stmt = $pdo->query('SELECT id, `from`, `to`, `subject`, `created_at` FROM emails ORDER BY created_at DESC');
    $emails = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Database error listing emails: ' . $e->getMessage());
    jsonError(500, 'Failed to retrieve emails.');
}

// 3. Return response
jsonResponse(200, [
    'status' => 'success',
    'data' => $emails
]);
