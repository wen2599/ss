<?php

/**
 * Sends a message to a specific Telegram chat.
 *
 * @param int $chatId The ID of the chat to send the message to.
 * @param string $text The text of the message to send.
 * @param array|null $keyboard Optional. A keyboard markup array to send with the message.
 * @return bool True on success, false on failure.
 */
function sendMessage(int $chatId, string $text, ?array $keyboard = null): bool
{
    $botToken = TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $postData = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML', // Allows for bold, italics, etc.
    ];

    if ($keyboard) {
        $postData['reply_markup'] = json_encode($keyboard);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Telegram sendMessage cURL Error: " . $error);
        return false;
    }

    $responseData = json_decode($response, true);
    if (!$responseData || !$responseData['ok']) {
        error_log("Telegram API Error: " . ($responseData['description'] ?? 'Unknown error'));
        return false;
    }

    return true;
}