<?php
declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Handles all communication with the Telegram Bot API.
 */
class TelegramService
{
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    private string $botToken;
    // LoggerInterface removed as per pure PHP requirements

    /**
     * TelegramService constructor.
     * @param string $botToken The Telegram bot token.
     */
    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
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
            $this->logError('Bot Token is not configured. Cannot send message.', ['chat_id' => $chatId]);
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

        } catch (Throwable $e) {
            $this->logError('Exception during sendMessage request.', [
                'chat_id' => $chatId,
                'exception' => $e,
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
     * @throws \RuntimeException If the request fails or the response is invalid.
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
        
        // Remove @ error suppression and explicitly handle false return
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            $error = error_get_last();
            $errorMessage = 'Failed to execute request. Network error or API unreachable.';
            if (isset($error['message'])) {
                $errorMessage .= ' PHP Error: ' . $error['message'];
            }
            throw new \RuntimeException($errorMessage);
        }

        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from Telegram API. Raw response: ' . $result . ' JSON Error: ' . json_last_error_msg());
        }

        return $response;
    }

    /**
     * Logs an informational message.
     * Output is controlled by APP_DEBUG environment variable.
     * @param string $message The message to log.
     * @param array $context Additional context for the log entry.
     */
    private function logInfo(string $message, array $context = []): void
    {
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            error_log('INFO: ' . $message . (empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE)));
        }
    }

    /**
     * Logs an error message.
     * @param string $message The message to log.
     * @param array $context Additional context for the log entry.
     */
    private function logError(string $message, array $context = []): void
    {
        $logMessage = 'ERROR: ' . $message;
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $e = $context['exception'];
            $logMessage .= sprintf(
                ' Exception: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        }
        $logMessage .= (empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
        error_log($logMessage);
    }
}
