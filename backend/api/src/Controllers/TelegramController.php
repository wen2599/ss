<?php
namespace App\Controllers;

use App\Controllers\LotteryController;
use Exception;

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
        $this->channelId = $_ENV['TELEGRAM_CHANNEL_ID'] ?? null;
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
        $text = trim($message['text'] ?? '');

        if (!$chatId || $text === '') {
            return;
        }

        // Check if the message is a command
        if (strpos($text, '/') === 0) {
            $this->_handleCommand($chatId, $text);
        } elseif ((string)$chatId === $this->channelId) {
            // If it's not a command and it's from the designated channel, parse and save results
            $this->_parseAndSaveLotteryResult($text);
        } else {
            // Message from an unexpected chat, notify admin if set
            if ($this->adminId) {
                $debugMessage = "Received a message from an unexpected chat.\n\n";
                $debugMessage .= "Chat ID: `{$chatId}`\n";
                $debugMessage .= "Configured TELEGRAM_CHANNEL_ID: `{$this->channelId}`\n\n";
                $debugMessage .= "Message: `{$text}`\n\n";
                $debugMessage .= "Please update your .env file with the correct Chat ID if this is the lottery channel, or ignore if this is a direct message.";
                $this->sendMessage($this->adminId, $debugMessage, 'MarkdownV2');
            }
        }
    }

    private function _handleCommand(string $chatId, string $commandText): void
    {
        // Extract command and arguments
        $parts = explode(' ', $commandText, 2);
        $command = strtolower($parts[0]); // e.g., /start
        $args = $parts[1] ?? '';

        switch ($command) {
            case '/start':
                $this->_handleStartCommand($chatId);
                break;
            case '/lottery':
                $this->_handleLotteryCommand($chatId);
                break;
            default:
                $this->sendMessage($chatId, "抱歉，我不认识这个命令。你可以尝试 /start 或 /lottery。", 'MarkdownV2');
                break;
        }
    }

    private function _handleStartCommand(string $chatId): void
    {
        $welcomeMessage = "欢迎使用开奖中心Bot！\n\n";
        $welcomeMessage .= "我可以为您提供最新的开奖结果。\n";
        $welcomeMessage .= "您可以尝试以下命令：\n";
        $welcomeMessage .= "/lottery - 获取最新开奖结果\n";
        $welcomeMessage .= "/start - 再次查看此欢迎信息\n\n";
        $welcomeMessage .= "如果您是管理员，请确保本Bot已被添加到开奖结果发布频道，并且已正确配置webhook。";

        $this->sendMessage($chatId, $welcomeMessage, 'MarkdownV2');
    }

    private function _handleLotteryCommand(string $chatId): void
    {
        try {
            $lotteryController = new LotteryController();
            $results = $lotteryController->fetchLatestResultsData();

            if (empty($results)) {
                $this->sendMessage($chatId, "抱歉，目前没有最新的开奖结果。", 'MarkdownV2');
                return;
            }

            $formattedResults = $this->_formatLotteryResults($results);
            $this->sendMessage($chatId, $formattedResults, 'MarkdownV2');

        } catch (Exception $e) {
            error_log('Error fetching lottery results for command: ' . $e->getMessage());
            $this->sendMessage($chatId, "抱歉，获取开奖结果时发生错误，请稍后再试。", 'MarkdownV2');
            if ($this->adminId) {
                $this->sendMessage($this->adminId, "Bot在处理 /lottery 命令时发生错误: " . $e->getMessage(), 'MarkdownV2');
            }
        }
    }

    private function _formatLotteryResults(array $results): string
    {
        $message = "*最新开奖结果*\n\n";
        foreach ($results as $result) {
            $message .= "*" . htmlspecialchars($result['lottery_type']) . "* - 第 " . htmlspecialchars($result['issue_number']) . " 期\n";
            $message .= "开奖号码: ";
            $numbers = explode(',', $result['winning_numbers']);
            foreach ($numbers as $number) {
                $message .= "`" . htmlspecialchars(trim($number)) . "` ";
            }
            $message .= "\n";
            
            // Attempt to decode number_colors_json and display zodiac/color if available
            if (!empty($result['number_colors_json'])) {
                try {
                    $numberDetails = json_decode($result['number_colors_json'], true);
                    if (is_array($numberDetails)) {
                        $detailsText = [];
                        foreach ($numbers as $number) {
                            $num = trim($number);
                            if (isset($numberDetails[$num])) {
                                $detail = $numberDetails[$num];
                                if (isset($detail['zodiac']) && isset($detail['color'])) {
                                    $detailsText[] = "`{$num}`: {$detail['zodiac']}/{$detail['color']}";
                                }
                            }
                        }
                        if (!empty($detailsText)) {
                            $message .= "详情: " . implode(", ", $detailsText) . "\n";
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error decoding number_colors_json: " . $e->getMessage());
                }
            }

            $message .= "开奖日期: " . date('Y-m-d H:i:s', strtotime($result['draw_date'])) . "\n";
            $message .= "\n";
        }
        return $message;
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
                    $numStr = str_pad((string)(int)$number, 2, '0', STR_PAD_LEFT); // Ensure two-digit format
                    $numberDetails[$numStr] = [
                        'zodiac' => $this->getZodiac($numStr),
                        'color' => $this->getColor($numStr)
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
                    error_log("Lottery result saved for type {$type}, issue {$issueNumber}.");

                } catch (\PDOException $e) {
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
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($options);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("Failed to send Telegram message to chat ID {$chatId}: Network error or unreachable Telegram API.");
            return;
        }

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
                error_log("Failed to send Telegram message to chat ID {$chatId}. HTTP Status: {$statusCode}. Error: {$errorMessage}. Response: {$result}");
            } else {
                error_log("Telegram message sent successfully to chat ID {$chatId}. Response: {$result}");
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
