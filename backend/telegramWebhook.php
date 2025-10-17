<?php

// Include the master configuration file
require_once __DIR__ . '/config.php';

// --- Security Validation ---
// Get the secret token from the request header
$secret_token_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
// Get the expected secret token from environment variables
$expected_secret_token = getenv('TELEGRAM_WEBHOOK_SECRET');

// If the expected token is set, we MUST validate it.
if ($expected_secret_token) {
    if ($secret_token_header !== $expected_secret_token) {
        // Log the security failure
        error_log('Telegram Webhook: ERROR - Invalid secret token provided.');
        // Respond with a 403 Forbidden error and stop execution.
        http_response_code(403);
        exit('Forbidden: Invalid secret token.');
    }
} else {
    // If the secret token is not configured in the environment, log a warning.
    // This is a security risk and should be configured for production.
    error_log('Telegram Webhook: WARNING - TELEGRAM_WEBHOOK_SECRET is not set. Skipping validation.');
}

// --- Process Incoming Update ---
// Get the raw JSON POST data from the request
$update_json = file_get_contents('php://input');
// Decode the JSON into a PHP object
$update = json_decode($update_json);

// Check if the update is valid
if (!$update) {
    // If the JSON is invalid, log the error and exit.
    error_log('Telegram Webhook: ERROR - Failed to decode incoming JSON.');
    http_response_code(400); // Bad Request
    exit('Bad Request: Invalid JSON.');
}

// Log the entire update object for debugging purposes
error_log('Telegram Webhook: Received update - ' . $update_json);

// --- Main Logic: Handle Different Update Types ---

// Check if the update is a standard message
if (isset($update->message)) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $text = $message->text ?? ''; // Use null-coalescing for non-text messages

    // Handle the /start command
    if ($text === '/start') {
        $reply_text = '✅ Bot is now connected and responding. Welcome!';
        sendTelegramMessage($chat_id, $reply_text);
    }
    // Add handlers for other commands here...

}
// Check if the update is a callback query (from an inline button)
elseif (isset($update->callback_query)) {
    $callback_query = $update->callback_query;
    $chat_id = $callback_query->message->chat->id;
    $callback_data = $callback_query->data;
    $callback_query_id = $callback_query->id;

    // Acknowledge the callback query to remove the "loading" icon on the user's client
    answerTelegramCallbackQuery($callback_query_id, 'Processing your request...');

    // Handle the callback data
    // Example: if ($callback_data === 'some_action') { ... }

    // For now, just send a confirmation message.
    sendTelegramMessage($chat_id, "Received your action: " . htmlspecialchars($callback_data));

}

// --- Final Response ---
// Always send a 200 OK response to Telegram to acknowledge receipt of the update.
// This prevents Telegram from re-sending the same update.
http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Update processed.']);

?>