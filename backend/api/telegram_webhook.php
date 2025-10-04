<?php
require_once __DIR__ . '/../init.php';

// --- Configuration ---
$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;

if (!$bot_token) {
    // Silently exit if the bot token is not configured.
    // In a production environment, you might want to log this error.
    http_response_code(500);
    error_log("TELEGRAM_BOT_TOKEN is not configured.");
    exit();
}

/**
 * Sends a reply message to a given Telegram chat.
 *
 * @param int|string $chat_id The ID of the chat to send the message to.
 * @param string $message The message text to send.
 * @param string $bot_token The Telegram bot token.
 */
function sendTelegramReply($chat_id, $message, $bot_token) {
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query([
                'chat_id' => $chat_id,
                'text' => $message,
            ]),
            'ignore_errors' => true, // Important to see the actual response from Telegram
        ],
    ];
    $context = stream_context_create($options);
    file_get_contents($api_url, false, $context);
}

// --- Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);

// Ensure the update is a valid message from a chat
if (!$update || !isset($update['message']['text']) || !isset($update['message']['chat']['id'])) {
    exit();
}

$message = $update['message']['text'];
$chat_id = $update['message']['chat']['id'];

// Process the command, e.g., "/update 20251005 1,2,3,4,5,6"
if (preg_match('/^\/update (\d{8,}) ([\d,\s]+)/', $message, $matches)) {
    $issue = $matches[1];
    $numbers_str = $matches[2];
    // Convert string of numbers to an array of integers
    $numbers = array_map('intval', explode(',', $numbers_str));

    // --- Validation ---
    if (count($numbers) !== 6) {
        sendTelegramReply($chat_id, "Error: Please provide exactly 6 numbers, separated by commas.", $bot_token);
        exit();
    }

    $new_data = [
        'issue' => $issue,
        'numbers' => $numbers
    ];

    $data_file = __DIR__ . '/../data/numbers.json';

    // --- Direct File Update ---
    if (file_put_contents($data_file, json_encode($new_data, JSON_PRETTY_PRINT))) {
        sendTelegramReply($chat_id, "Success! Lottery numbers for issue {$issue} have been updated.", $bot_token);
    } else {
        sendTelegramReply($chat_id, "Error: A server-side error occurred. Could not write to the data file.", $bot_token);
    }
} else {
    // If the user sends an "/update" command with the wrong format, give feedback
    if (strpos(trim($message), '/update') === 0) {
        sendTelegramReply($chat_id, "Invalid command format. Please use: /update YYYYMMDD 1,2,3,4,5,6", $bot_token);
    }
    // For any other message, you can choose to ignore it or send a help message.
}
?>