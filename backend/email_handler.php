<?php

// Bootstrap the application
require_once __DIR__ . '/config.php';

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET'); 
$receivedToken = $_SERVER['HTTP_X_EMAIL_HANDLER_SECRET_TOKEN'] ?? '';

// Use hash_equals for timing-attack-safe comparison
if (empty($secretToken) || !hash_equals($secretToken, $receivedToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret token.']);
    exit;
}

// --- Process and Store Email Data ---

// Get the raw POST data (the email JSON from the worker).
$emailJson = file_get_contents('php://input');

// Basic validation to ensure we received something.
if (empty($emailJson)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: No data received.']);
    exit;
}

// At this point, you would insert the raw email data into the database.
// For now, as requested, we will log it to a file as a placeholder for DB storage.
// The raw $emailJson (containing from, to, subject, body) is what we will store.

$logFile = __DIR__ . '/email_log.txt';
$currentTime = date('Y-m-d H:i:s');
// We log the raw JSON, which is exactly what we'd store in a 'raw_email' column in the DB.
$logEntry = "--- [{$currentTime}] Email Received ---\n{$emailJson}\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// --- Respond to the Worker ---
// Acknowledge receipt of the email.
http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Email received and logged for future processing.']);

?>