<?php

// Set up error reporting for production.
ini_set('display_errors', 0);
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// --- Core Dependencies ---
// The webhook is a standalone script and only needs the core configuration and helpers.
require_once __DIR__ . '/config.php';

// --- Security Check ---
// Validate the secret token to ensure the request is from Telegram.
$secretTokenFromEnv = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (empty($secretTokenFromEnv) || !hash_equals($secretTokenFromEnv, $receivedToken)) {
    // If the token is missing or doesn't match, deny access.
    http_response_code(403);
    error_log("Forbidden: Incorrect or missing secret token on webhook access.");
    exit('Forbidden');
}

// --- Process Incoming Update ---
$raw_input = file_get_contents('php://input');
if (empty($raw_input)) {
    http_response_code(200);
    exit();
}

$update = json_decode($raw_input, true);
if ($update === null) {
    error_log("Failed to decode JSON from Telegram update: " . $raw_input);
    http_response_code(400); // Bad Request
    exit();
}

// --- Command Processing ---
// We only care about messages from the authorized admin.
if (isset($update['message'])) {
    $message = $update['message'];
    $userId = $message['from']['id'];
    $chatId = $message['chat']['id'];
    $command = trim($message['text'] ?? '');
    $adminUserId = getenv('TELEGRAM_ADMIN_ID');

    // Verify the user is the admin.
    if (!empty($adminUserId) && (string)$userId === (string)$adminUserId && !empty($command)) {
        try {
            processCommand($chatId, $userId, $command);
        } catch (Throwable $e) {
            error_log("Error in processCommand: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            // Optionally, send a generic error message to the admin.
            sendTelegramMessage($chatId, "An error occurred while processing your command.");
        }
    }
}

// Acknowledge receipt to Telegram's server to prevent webhook retries.
http_response_code(200);
echo json_encode(['status' => 'ok']);

?>