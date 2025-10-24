<?php
namespace App\Controllers;

class TelegramController extends BaseController {

    private $botToken;
    private $channelId;
    private $adminId;
    private $zodiacMap = [
        '鼠' => ['06', '18', '30', '42'], '牛' => ['05', '17', '29', '41'], '虎' => ['04', '16', '28', '40'],
        '兔' => ['03', '15', '27', '39'], '龙' => ['02', '14', '26', '38'], '蛇' => ['01', '13', '25', '37', '49'],
        '马' => ['12', '24', '36', '48'], '羊' => ['11', '23', '35', '47'], '猴' => ['10', '22', '34', '46'],
        '鸡' => ['09', '21', '33', '45'], '狗' => ['08', '20', '32', '44'], '猪' => ['07', '19', '31', '43']
    ];
    private $colorMap = [
        '红' => ['01', '02', '07', '08', '12', '13', '18', '19', '23', '24', '29', '30', '34', '35', '40', '45', '46'],
        '蓝' => ['03', '04', '09', '10', '14', '15', '20', '25', '26', '31', '36', '37', '41', '42', '47', '48'],
        '绿' => ['05', '06', '11', '16', '17', '21', '22', '27', '28', '32', '33', '38', '39', '43', '44', '49']
    ];

    public function __construct()
    {
        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $this->channelId = $_ENV['TELEGRAM_CHANNEL_ID'] ?? null; // Corrected from LOTTERY_CHANNEL_ID
        $this->adminId = $_ENV['TELEGRAM_ADMIN_ID'] ?? null;

        if (!$this->botToken || !$this->channelId || !$this->adminId) {
            error_log('Telegram Bot configuration (TELEGRAM_BOT_TOKEN, TELEGRAM_CHANNEL_ID, TELEGRAM_ADMIN_ID) is incomplete.');
            send_json_error(503, 'Service Unavailable: Telegram Bot is not configured correctly.');
        }
    }

    public function handleWebhook(array $update): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? $update['edited_channel_post'] ?? null;

        if (!$message) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        if (!$chatId || !$text) {
            return;
        }

        if ((string)$chatId === $this->channelId) {
            $this->_parseAndSaveLotteryResult($text);
        } else {
            if ($this->adminId) {
                $debugMessage = "Received a message from an unexpected channel.\n\n";
                $debugMessage .= "Chat ID: `{$chatId}`\n";
                $debugMessage .= "Configured TELEGRAM_CHANNEL_ID: `{$this->channelId}`\n\n";
                $debugMessage .= "Please update your .env file with the correct Chat ID if this is the lottery channel.";
                $this->sendMessage($this->adminId, $debugMessage, 'MarkdownV2');
            }
        }
    }

    private function _parseAndSaveLotteryResult(string $text): void
    {
        $patterns = [
            '新澳' => '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
            '香港' => '/香港六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
            '老澳' => '/老澳\d{2}\.\d{2}第:(\d+)\s*期开奖结果:\s*([\d\s]+)/'
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $issueNumber = $matches[1];
                $numbers = preg_split('/\s+/', trim($matches[2]));
                $winningNumbers = implode(',', $numbers);

                $numberDetails = [];
                foreach ($numbers as $number) {
                    $numberDetails[$number] = [
                        'zodiac' => $this->getZodiac($number),
                        'color' => $this->getColor($number)
                    ];
                }
                $numberColorsJson = json_encode($numberDetails, JSON_UNESCAPED_UNICODE);

                try {
                    $pdo = $this->getDbConnection();
                    $stmt = $pdo->prepare(
                        "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, number_colors_json, draw_date)
                         VALUES (?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE winning_numbers = VALUES(winning_numbers), number_colors_json = VALUES(number_colors_json), draw_date = NOW()"
                    );
                    $stmt->execute([$type, $issueNumber, $winningNumbers, $numberColorsJson]);
                } catch (\PDOException $e) {
                    // Log to file, and optionally send a message to admin
                    error_log('Failed to save lottery result: ' . $e->getMessage());
                    if ($this->adminId) {
                        $this->sendMessage($this->adminId, 'Failed to save lottery result: ' . $e->getMessage());
                    }
                }
                break;
            }
        }
    }

    private function sendMessage(string $chatId, string $text, string $parseMode = null): void
    {
        if (!$this->botToken) {
            error_log('Telegram Bot Token is not set. Cannot send message.');
            return;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'ignore_errors' => true // This allows us to get the response even on HTTP errors.
            ]
        ];
        $context = stream_context_create($options);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("Failed to send Telegram message to chat ID {$chatId}: Network error or unreachable Telegram API.");
            return;
        }

        // Check HTTP response headers for status code
        if (isset($http_response_header)) {
            $statusCode = 0;
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                    $statusCode = (int)$matches[1];
                    break;
                }
            }

            if ($statusCode !== 200) {
                $responseContent = json_decode($result, true);
                $errorMessage = $responseContent['description'] ?? 'Unknown Telegram API error';
                error_log("Failed to send Telegram message to chat ID {$chatId}. HTTP Status: {$statusCode}. Error: {$errorMessage}");
            }
        } else {
            error_log("Failed to send Telegram message to chat ID {$chatId}: Could not retrieve HTTP response headers.");
        }
    }

    private function getZodiac(string $number): ?string
    {
        foreach ($this->zodiacMap as $zodiac => $numbers) {
            if (in_array($number, $numbers)) {
                return $zodiac;
            }
        }
        return null;
    }

    private function getColor(string $number): ?string
    {
        foreach ($this->colorMap as $color => $numbers) {
            if (in_array($number, $numbers)) {
                return $color;
            }
        }
        return null;
    }
}
