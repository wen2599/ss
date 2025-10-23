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

// 2. Input Validation (ID from URL)
$emailId = $_GET['id'] ?? null;
if (!$emailId || !filter_var($emailId, FILTER_VALIDATE_INT)) {
    jsonError(400, 'Invalid or missing email ID.');
}

$pdo = getDbConnection();

// 3. Fetch the specific email from the database
try {
    $stmt = $pdo->prepare('SELECT * FROM emails WHERE id = :id');
    $stmt->execute(['id' => $emailId]);
    $email = $stmt->fetch();

    if (!$email) {
        jsonError(404, 'Email not found.');
    }
} catch (PDOException $e) {
    error_log('Database error fetching email: ' . $e->getMessage());
    jsonError(500, 'Failed to retrieve the email.');
}

// 4. Return response
jsonResponse(200, [
    'status' => 'success',
    'data' => $email
]);
