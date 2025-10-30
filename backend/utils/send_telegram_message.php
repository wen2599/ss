<?php
// File: backend/utils/send_telegram_message.php
// Description: A utility function to send messages via the Telegram Bot API.

require_once __DIR__ . '/../config/secrets.php';

/**
 * Sends a message to a specified Telegram chat.
 *
 * @param int $chat_id The ID of the chat to send the message to.
 * @param string $text The message text.
 * @param array $options Optional parameters like 'parse_mode'.
 * @return bool True on success, false on failure.
 */
function send_telegram_message($chat_id, $text, $options = []) {
    $token = get_telegram_token();
    if (!$token) {
        // Log error: Telegram token is not configured.
        error_log("Telegram Bot Token is not configured.");
        return false;
    }

    $api_url = "https://api.telegram.org/bot{$token}/sendMessage";

    $payload = array_merge([
        'chat_id' => $chat_id,
        'text' => $text
    ], $options);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Telegram API Error: Failed to send message. HTTP Code: {$http_code}. Response: {$response}. cURL Error: {$curl_error}");
        return false;
    }

    return true;
}

?>
