<?php
// Included from /api/index.php

// This script handles the receiving of emails (e.g., from a webhook).

// --- Security Check (Optional but Recommended) ---
// Example: Verify a secret passed in headers to ensure the request is from a trusted source.
/*
$workerSecret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$expectedSecret = $_ENV['WORKER_SECRET'] ?? '';
if (empty($workerSecret) || !
hash_equals($expectedSecret, $workerSecret)) {
    jsonError(403, 'Forbidden: Invalid or missing secret.');
}
*/

// --- Input Validation ---
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['sender']) || !isset($input['subject']) || !isset($input['body'])) {
    jsonError(400, 'Missing required email fields: sender, subject, and body.');
}

$sender = trim($input['sender']);
$subject = trim($input['subject']);
$body = trim($input['body']);
$isPrivate = filter_var($input['is_private'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (empty($sender) || empty($subject) || empty($body)) {
    jsonError(400, 'Sender, subject, and body cannot be empty.');
}

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        "INSERT INTO emails (sender, subject, body, is_private) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$sender, $subject, $body, $isPrivate ? 1 : 0]);

    $emailId = $pdo->lastInsertId();

    // --- Success Response ---
    jsonResponse(201, [
        'status' => 'success',
        'message' => 'Email received and stored successfully.',
        'data' => ['email_id' => $emailId]
    ]);

} catch (PDOException $e) {
    error_log("Receive-Email DB Error: " . $e->getMessage());
    jsonError(500, 'Database error while storing the email.');
} catch (Throwable $e) {
    error_log("Receive-Email Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred.');
}
