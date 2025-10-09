<?php

// API handler for incoming Telegram Bot Webhooks

require_once __DIR__ . '/../core/Response.php';

// --- Configuration & Security ---

// These are now loaded from .env via config.php
$secretToken = TELEGRAM_WEBHOOK_SECRET;
$allowedChannelId = TELEGRAM_CHANNEL_ID;

// The header that contains the secret token
$secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

// Path to the file where the latest lottery number will be stored
$storagePath = __DIR__ . '/../../data/lottery_latest.json';

// --- Logging Setup ---
$logFile = __DIR__ . '/../../telegram.log';
function write_log($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    // Use JSON_UNESCAPED_UNICODE for better readability of non-English characters
    $logEntry = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $message;
    file_put_contents($logFile, "{$timestamp} - {$logEntry}\n", FILE_APPEND);
}

write_log("--- New Webhook Request Received ---");

// --- Validation ---

// 1. Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    write_log("Request rejected: Method was {$_SERVER['REQUEST_METHOD']}, not POST.");
    Response::json(['error' => 'Method Not Allowed'], 405);
    exit;
}

// 2. Validate the secret token to ensure the request is from Telegram
write_log("Validating Secret Token...");
write_log("  - Header Value (HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN): '{$secretHeader}'");
write_log("  - Expected Value (from .env): '" . substr($secretToken, 0, 5) . "...'");

if (!$secretToken || $secretToken !== $secretHeader) {
    write_log("Validation FAILED: Invalid secret token.");
    error_log('Invalid webhook secret token attempt.');
    Response::json(['error' => 'Unauthorized'], 401);
    exit;
}
write_log("Validation PASSED: Secret token is valid.");


// --- Process the Request ---

$update = $GLOBALS['requestBody'] ?? null;
write_log("Received update payload:");
write_log($update);


if (!$update) {
    write_log("Request rejected: No data payload received.");
    Response::json(['error' => 'No data received'], 400);
    exit;
}

// --- Extract and Validate the Message ---

if (isset($update['channel_post'])) {
    $channelPost = $update['channel_post'];
    $channelId = $channelPost['chat']['id'] ?? null;
    $messageText = trim($channelPost['text'] ?? '');

    // 3. Validate Channel ID
    write_log("Validating Channel ID...");
    write_log("  - Received Channel ID: {$channelId}");
    write_log("  - Allowed Channel ID (from .env): {$allowedChannelId}");

    if (!$allowedChannelId || $channelId != $allowedChannelId) {
        write_log("Validation FAILED: Message from unauthorized channel ID.");
        error_log("Message from unauthorized channel ID: {$channelId}. Allowed: {$allowedChannelId}");
        Response::json(['error' => 'Forbidden: Message from wrong channel'], 403);
        exit;
    }
    write_log("Validation PASSED: Channel ID is valid.");

    if ($messageText) {
        write_log("Processing message text: '{$messageText}'");
        $receivedAt = date('Y-m-d H:i:s T');

        $dataToStore = [
            'lottery_number' => $messageText,
            'received_at_utc' => $receivedAt
        ];

        if (file_put_contents($storagePath, json_encode($dataToStore), LOCK_EX)) {
            write_log("Successfully stored data to {$storagePath}.");
            Response::json(['status' => 'success', 'message' => 'Data stored']);
        } else {
            write_log("Error: Failed to write to storage file: {$storagePath}");
            error_log('Failed to write to storage file: ' . $storagePath);
            Response::json(['error' => 'Internal Server Error'], 500);
        }
    } else {
        write_log("Ignoring empty message from channel post.");
        Response::json(['status' => 'ok', 'message' => 'Empty message, ignored']);
    }

} else {
    write_log("Ignoring payload because it is not a 'channel_post'.");
    Response::json(['status' => 'ok', 'message' => 'Payload not a channel post, ignored']);
}

write_log("--- Webhook Request Finished ---\n");