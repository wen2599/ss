<?php

// Set up error reporting for debugging.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load the core configuration and helper functions.
require_once __DIR__ . '/config.php';

// --- Security Check ---
// Validate the secret token to ensure the request is from Telegram.
$secretTokenFromEnv = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (empty($secretTokenFromEnv) || $receivedToken !== $secretTokenFromEnv) {
    // If the token is missing or doesn't match, deny access.
    http_response_code(403);
    error_log("Forbidden: Incorrect or missing secret token.");
    exit('Forbidden');
}

// --- Process Incoming Update ---
// Get the raw JSON input from the request body.
$raw_input = file_get_contents('php://input');
if (empty($raw_input)) {
    // If the input is empty, do nothing.
    http_response_code(200);
    exit();
}

// Decode the JSON update from Telegram.
$update = json_decode($raw_input, true);
if ($update === null) {
    error_log("Failed to decode JSON from Telegram update.");
    http_response_code(400); // Bad Request
    exit();
}

// --- Extract Key Information ---
// Check for a message object in the update.
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $command = trim($message['text'] ?? '');

    // --- Admin Verification ---
    // Ensure the message is from the authorized admin.
    $adminUserId = getenv('TELEGRAM_ADMIN_ID');
    if (empty($adminUserId) || (string)$userId !== (string)$adminUserId) {
        // Silently ignore messages from non-admins but acknowledge receipt.
        http_response_code(200);
        exit();
    }

    // --- Command Processing ---
    // If everything is valid, process the command.
    if (!empty($command)) {
        try {
            processCommand($chatId, $userId, $command);
        } catch (Throwable $e) {
            // Log any errors that occur during command processing.
            error_log("Error in processCommand: " . $e->getMessage());
            // Optionally, send an error message to the admin.
            sendTelegramMessage($chatId, "An error occurred while processing your command.");
        }
    }
}

// Acknowledge receipt to Telegram's server to prevent webhook retries.
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>