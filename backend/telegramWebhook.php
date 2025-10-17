<?php
// Main handler for Telegram Webhook

// Ensure the configuration and all helpers are loaded.
require_once __DIR__ . '/config.php';

// --- Get Webhook Data ---
$rawBody = file_get_contents('php://input');
$update = json_decode($rawBody, true);

// If the update is invalid, log it and exit.
if (!$update) {
    error_log("telegramWebhook: Received invalid or empty update.");
    http_response_code(200); // Respond 200 to prevent Telegram from retrying.
    exit;
}

// --- Extract Key Information ---
$message = $update['message'] ?? null;
$callbackQuery = $update['callback_query'] ?? null;
$user = null;
$chatId = null;
$userId = null;
$commandOrText = null;
$isCallback = false;

if ($callbackQuery) {
    $user = $callbackQuery['from'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $user['id'];
    $commandOrText = $callbackQuery['data'];
    $isCallback = true;
    // Answer the callback query to stop the loading animation on the button.
    answerTelegramCallbackQuery($callbackQuery['id']);
} elseif ($message) {
    $user = $message['from'];
    $chatId = $message['chat']['id'];
    $userId = $user['id'];
    $commandOrText = $message['text'] ?? '';
}

// If we couldn't identify a user or chat, exit.
if (!$userId || !$chatId) {
    error_log("telegramWebhook: Could not extract user_id or chat_id from the update.");
    http_response_code(200);
    exit;
}

// --- Security Check: Only Admin Can Interact ---
$adminId = getenv('TELEGRAM_ADMIN_ID');
if (!$adminId) {
    error_log("CRITICAL: TELEGRAM_ADMIN_ID is not set in environment.");
    sendTelegramMessage($chatId, "⚠️ Server misconfiguration: Admin ID not set. Please inform the administrator.");
    http_response_code(200); // Respond 200 to prevent Telegram from retrying.
    exit;
}

if ($userId != $adminId) {
    sendTelegramMessage($chatId, "⛔️ Access Denied. You are not authorized to use this bot.");
    error_log("telegramWebhook: Unauthorized access attempt by user ID {$userId}. Expected admin ID: {$adminId}");
    http_response_code(200);
    exit;
}

// --- Process Logic ---
try {
    $conn = get_db_connection(); // Ensure we have a database connection.
    // Check if get_db_connection returned an error array
    if (is_array($conn) && isset($conn['db_error'])) {
        error_log("telegramWebhook: Database connection failed: " . $conn['db_error']);
        sendTelegramMessage($chatId, "❌ Database connection is currently unavailable. Please try again later.");
        http_response_code(200);
        exit;
    }

    $userState = getUserState($userId); // Check if the user is in a multi-step interaction.

    if ($userState) {
        // Handle stateful interactions (e.g., awaiting API key input).
        handleStatefulInteraction($conn, $userId, $chatId, $commandOrText, $userState);
    } else {
        // Process regular commands and callbacks.
        processCommand($conn, $userId, $chatId, $commandOrText, $isCallback);
    }
} catch (Exception $e) {
    // Log any unexpected errors during processing.
    error_log("telegramWebhook: Exception caught - " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    // Optionally, send a generic error message to the admin.
    sendTelegramMessage($chatId, "⚠️ An unexpected error occurred. Please check the server logs.");
}

// --- Final Acknowledgment ---
// Always respond with 200 OK to let Telegram know the webhook was received successfully.
http_response_code(200);
echo 'ok';

?>