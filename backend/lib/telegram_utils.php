<?php
// backend/lib/telegram_utils.php

/**
 * Sends a message to a specified Telegram user.
 *
 * @param string $bot_token The Telegram Bot Token.
 * @param string $chat_id The user ID to send the message to.
 * @param string $message The message text.
 * @return bool True on success, false on failure.
 */
function send_telegram_message(string $bot_token, string $chat_id, string $message): bool
{
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown', // Optional: for formatting
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10, // 10-second timeout
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($api_url, false, $context);

    if ($result === false) {
        error_log("Failed to send Telegram message.");
        return false;
    }

    $response = json_decode($result, true);
    if (!isset($response['ok']) || $response['ok'] !== true) {
        error_log("Telegram API returned an error: " . ($response['description'] ?? 'Unknown error'));
        return false;
    }

    return true;
}
