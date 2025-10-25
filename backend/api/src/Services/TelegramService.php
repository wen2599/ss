<?php
declare(strict_types=1);

namespace App\Services;

class TelegramService
{
    private string $botToken;
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    /**
     * Sends a message to a specified chat via the Telegram Bot API.
     *
     * @param string $chatId The ID of the chat to send the message to.
     * @param string $text The message text.
     * @param string|null $parseMode The parsing mode for the message text (e.g., 'MarkdownV2', 'HTML').
     * @return bool True on success, false on failure.
     */
    public function sendMessage(string $chatId, string $text, ?string $parseMode = null): bool
    {
        $url = self::API_BASE_URL . $this->botToken . '/sendMessage';
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Telegram API Error: Failed to send message. HTTP {$httpCode}. Response: {$response}");
            return false;
        }

        return true;
    }
}
