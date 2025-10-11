<?php

/**
 * Sends a generic request to the Telegram Bot API.
 *
 * @param string $method The API method to call (e.g., 'sendMessage', 'setWebhook').
 * @param array $data The data to send with the request.
 * @return array|null The decoded JSON response from the API, or null on failure.
 */
function sendTelegramRequest(string $method, array $data = []): ?array
{
    $botToken = TELEGRAM_BOT_TOKEN ?? null;
    if (!$botToken || $botToken === 'your_telegram_bot_token_here') {
        error_log("Telegram Bot Token is not configured. Please set it in the .env file.");
        return null;
    }

    $url = "https://api.telegram.org/bot{$botToken}/{$method}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MyCustomBot/1.0');

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log("Telegram Request cURL Error for method {$method}: " . $error);
        return null;
    }

    if ($http_code !== 200) {
        error_log("Telegram API request for method {$method} failed with HTTP code {$http_code}. Response: {$response}");
        // Decode to try and get a description
        $decoded = json_decode($response, true);
        return $decoded ?: null;
    }

    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to decode JSON response from Telegram for method {$method}. Response: {$response}");
        return null;
    }

    // Return the full response, the caller can check for 'ok' status.
    return $responseData;
}


/**
 * Sends a message to a specific Telegram chat.
 * This is a convenience wrapper around sendTelegramRequest.
 *
 * @param int $chatId The ID of the chat to send the message to.
 * @param string $text The text of the message to send.
 * @param array|null $keyboard Optional. A keyboard markup array to send with the message.
 * @return bool True on success, false on failure.
 */
function sendMessage(int $chatId, string $text, ?array $keyboard = null): bool
{
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    $response = sendTelegramRequest('sendMessage', $data);

    // A successful response from Telegram has the 'ok' key set to true.
    return $response && isset($response['ok']) && $response['ok'];
}