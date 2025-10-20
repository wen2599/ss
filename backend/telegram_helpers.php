<?php
// backend/telegram_helpers.php
// Contains helper functions for interacting with the Telegram Bot API.

require_once __DIR__ . '/api_curl_helper.php'; // For making cURL requests
require_once __DIR__ . '/logging_helper.php'; // For custom logging

// Load Telegram Bot Token from environment variables
if (!defined('TELEGRAM_BOT_TOKEN') && isset($_ENV['TELEGRAM_BOT_TOKEN'])) {
    define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN']);
}

// Load Backend Public URL from environment variables
if (!defined('BACKEND_PUBLIC_URL') && isset($_ENV['BACKEND_PUBLIC_URL'])) {
    define('BACKEND_PUBLIC_URL', $_ENV['BACKEND_PUBLIC_URL']);
}

/**
 * Sends a message to a Telegram chat.
 * @param string $chatId The ID of the chat to send the message to.
 * @param string $text The text of the message to be sent.
 * @param array $extraParams Optional: additional parameters for the sendMessage method.
 * @return array The decoded JSON response from the Telegram API.
 */
function sendTelegramMessage(string $chatId, string $text, array $extraParams = []): array
{
    if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
        custom_log("Telegram Bot Token is not defined.", 'ERROR');
        return ['ok' => false, 'description' => 'Bot token missing'];
    }

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $params = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ], $extraParams);

    return postRequest($url, $params);
}

/**
 * Sets the Telegram webhook URL.
 * @param string $webhookUrl The URL to which Telegram should send updates.
 * @param string $secretToken The secret token to be sent with every webhook update.
 * @return array The decoded JSON response from the Telegram API.
 */
function setTelegramWebhook(string $webhookUrl, string $secretToken):
    array
{
    if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
        custom_log("Telegram Bot Token is not defined.", 'ERROR');
        return ['ok' => false, 'description' => 'Bot token missing'];
    }

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook";
    $params = [
        'url' => $webhookUrl,
        'secret_token' => $secretToken,
    ];
    return postRequest($url, $params);
}

/**
 * Removes the Telegram webhook.
 * @return array The decoded JSON response from the Telegram API.
 */
function deleteTelegramWebhook(): array
{
    if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
        custom_log("Telegram Bot Token is not defined.", 'ERROR');
        return ['ok' => false, 'description' => 'Bot token missing'];
    }

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/deleteWebhook";
    return postRequest($url, []);
}

/**
 * Retrieves information about the webhook.
 * @return array The decoded JSON response from the Telegram API.
 */
function getTelegramWebhookInfo(): array
{
    if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) {
        custom_log("Telegram Bot Token is not defined.", 'ERROR');
        return ['ok' => false, 'description' => 'Bot token missing'];
    }
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getWebhookInfo";
    return getRequest($url);
}

?>
