<?php
declare(strict_types=1);

// Assumes jsonResponse and jsonError functions are available from index.php
// Assumes getDbConnection() is available from index.php

$pdo = getDbConnection();

// --- Authentication ---
// Get the secret from the X-Worker-Secret header
$workerSecret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? null;
$expectedSecret = $_ENV['WORKER_SECRET'] ?? null;

if (!$workerSecret || !$expectedSecret || !hash_equals($expectedSecret, $workerSecret)) {
    error_log("Unauthorized email worker request. Provided secret: {$workerSecret}");
    jsonError(401, 'Unauthorized');
}

// --- Input Processing ---
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonError(400, 'Invalid JSON payload.');
}

// Validate essential fields
$from = $input['from'] ?? null;
$to = $input['to'] ?? null;
$subject = $input['subject'] ?? null;
$body = $input['body'] ?? ''; // Default to empty string
$raw = $input['raw'] ?? '';   // Default to empty string

if (!$from || !$to || !$subject) {
    jsonError(400, 'Missing required fields: from, to, subject.');
}

// --- Database Insertion ---
try {
    $stmt = $pdo->prepare(
        'INSERT INTO emails (`from`, `to`, `subject`, `body`, `raw`) VALUES (:from, :to, :subject, :body, :raw)'
    );
    $stmt->execute([
        ':from' => $from,
        ':to' => $to,
        ':subject' => $subject,
        ':body' => $body,
        ':raw' => $raw,
    ]);

    $emailId = $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Database error saving email: ' . $e->getMessage());
    jsonError(500, 'Failed to save email to database.');
}

// --- Success Response ---
jsonResponse(201, [
    'status' => 'success',
    'message' => 'Email received and stored successfully.',
    'data' => ['email_id' => $emailId]
]);
