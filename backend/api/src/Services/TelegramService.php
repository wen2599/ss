<?php
namespace App\Services;

use Psr\Log\LoggerInterface;

/**
 * Handles all communication with the Telegram Bot API.
 */
class TelegramService
{
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    private string $botToken;
    private ?LoggerInterface $logger;

    public function __construct(string $botToken, ?LoggerInterface $logger = null)
    {
        $this->botToken = $botToken;
        $this->logger = $logger;
    }

    /**
     * Sends a text message to a specified chat.
     *
     * @param string $chatId The ID of the chat to send the message to.
     * @param string $text The text of the message to send.
     * @param string|null $parseMode Optional. Mode for parsing entities in the message text.
     * @return bool True on success, false on failure.
     */
    public function sendMessage(string $chatId, string $text, ?string $parseMode = null): bool
    {
        if (empty($this->botToken)) {
            $this->logError('Bot Token is not configured. Cannot send message.');
            return false;
        }

        $url = self::API_BASE_URL . $this->botToken . '/sendMessage';

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        try {
            $response = $this->executeRequest($url, $payload);

            if (!$response['ok']) {
                $this->logError('Telegram API returned an error.', [
                    'chat_id' => $chatId,
                    'error_code' => $response['error_code'] ?? 'N/A',
                    'description' => $response['description'] ?? 'No description provided.',
                ]);
                return false;
            }

            $this->logInfo('Message sent successfully.', ['chat_id' => $chatId]);
            return true;

        } catch (\Exception $e) {
            $this->logError('Exception during sendMessage request.', [
                'chat_id' => $chatId,
                'exception_message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Executes a POST request to the Telegram API.
     *
     * @param string $url The full URL to send the request to.
     * @param array $payload The data to be sent in the request body.
     * @return array The decoded JSON response from the API.
     * @throws \Exception If the request fails or the response is invalid.
     */
    private function executeRequest(string $url, array $payload): array
    {
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'ignore_errors' => true, // Allows us to handle non-2xx responses gracefully
                'timeout' => 10, // 10-second timeout
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            throw new \Exception('Failed to execute request. Network error or API unreachable.');
        }

        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from Telegram API. Raw response: ' . $result);
        }

        return $response;
    }

    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        } else {
            error_log("INFO: " . $message . " " . json_encode($context));
        }
    }

    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        } else {
            error_log("ERROR: " . $message . " " . json_encode($context));
        }
    }
}
