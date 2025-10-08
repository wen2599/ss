<?php
// backend/lib/telegram_utils.php

/**
 * Sends a message to a specified Telegram user.
 *
 * @param string $chat_id The user ID to send the message to.
 * @param string $message The message text.
 * @param array|null $reply_markup Optional keyboard markup.
 * @return bool True on success, false on failure.
 */
function send_telegram_message(string $chat_id, string $message, ?array $reply_markup = null): bool
{
    $api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown',
    ];

    // Add the keyboard to the request body if it's provided
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data),
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
