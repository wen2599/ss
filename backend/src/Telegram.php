<?php
/**
 * Namespace for application library files.
 */
namespace App;

/**
 * Class Telegram
 *
 * A utility class for interacting with the Telegram Bot API.
 * It provides static methods to send messages, answer callback queries, and edit messages.
 */
class Telegram {

    /**
     * The base URL for the Telegram Bot API.
     */
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    /**
     * Sends a request to the Telegram API.
     *
     * @param string $method The API method to call (e.g., 'sendMessage').
     * @param array $data The data to send with the request.
     * @return array|null The decoded JSON response from the API, or null on failure.
     */
    private static function sendRequest(string $method, array $data): ?array {
        global $log; // Use the global logger from init.php

        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        if (!$botToken) {
            $log->error("Telegram Bot Token is not configured.");
            return null;
        }

        $url = self::API_BASE_URL . $botToken . '/' . $method;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $log->error("cURL error during Telegram API request.", ['method' => $method, 'error' => $curlError]);
            return null;
        }

        $responseData = json_decode($responseBody, true);

        if ($httpCode !== 200 || !$responseData['ok']) {
            $log->error("Telegram API returned an error.", [
                'method' => $method,
                'http_code' => $httpCode,
                'response' => $responseData ?? ['raw' => $responseBody]
            ]);
            return null;
        }

        $log->info("Successfully sent Telegram API request.", ['method' => $method]);
        return $responseData;
    }

    /**
     * Sends a text message to a specified chat.
     *
     * @param int|string $chatId The ID of the chat to send the message to.
     * @param string $text The text of the message. Supports Markdown.
     * @param string|null $replyMarkup A JSON-encoded inline keyboard.
     * @return array|null The API response.
     */
    public static function sendMessage($chatId, string $text, ?string $replyMarkup = null): ?array {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ];
        if ($replyMarkup) {
            $data['reply_markup'] = json_decode($replyMarkup, true); // Must be an array, not string
        }
        return self::sendRequest('sendMessage', $data);
    }

    /**
     * Answers a callback query (e.g., from a button press).
     *
     * @param string $callbackQueryId The ID of the callback query.
     * @param string $text The text to show in the notification.
     * @param bool $showAlert Whether to show the text as an alert (true) or a toast (false).
     * @return array|null The API response.
     */
    public static function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): ?array {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert
        ];
        return self::sendRequest('answerCallbackQuery', $data);
    }

    /**
     * Edits the reply markup (e.g., removes buttons) of a message.
     *
     * @param int|string $chatId The ID of the chat containing the message.
     * @param int $messageId The ID of the message to edit.
     * @param string|null $replyMarkup The new JSON-encoded inline keyboard.
     * @return array|null The API response.
     */
    public static function editMessageReplyMarkup($chatId, int $messageId, ?string $replyMarkup = null): ?array {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
        if ($replyMarkup) {
            $data['reply_markup'] = json_decode($replyMarkup, true);
        }
        return self::sendRequest('editMessageReplyMarkup', $data);
    }
}
?>