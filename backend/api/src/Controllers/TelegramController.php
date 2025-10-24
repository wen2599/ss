<?php
namespace App\Controllers;

class TelegramController extends BaseController {

    private $botToken;
    private $channelId;
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
        $this->channelId = $_ENV['LOTTERY_CHANNEL_ID'] ?? null;
        if (!$this->channelId) {
            error_log("[ERROR] LOTTERY_CHANNEL_ID is not set. The bot will not be able to identify the correct channel.");
        }
    }

    public function handleWebhook(array $update): void
    {
        error_log("[INFO] Handling webhook update: " . json_encode($update));

        $message = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? $update['edited_channel_post'] ?? null;

        if (!$message) {
            error_log("[INFO] Webhook update is not a processable message type. Skipping.");
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        if (!$chatId || !$text) {
            error_log("[INFO] Message does not contain a chat ID or text. Skipping.");
            return;
        }

        error_log("[INFO] Received message from chat ID: {$chatId}. Configured channel ID: {$this->channelId}");

        if ((string)$chatId === $this->channelId) {
            error_log("[INFO] Message is from the target lottery channel. Attempting to parse.");
            $this->_parseAndSaveLotteryResult($text);
        } else {
            error_log("[INFO] Message is not from the target lottery channel. Skipping.");
        }
    }

    private function _parseAndSaveLotteryResult(string $text): void
    {
        $patterns = [
            '新澳' => '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
            '香港' => '/香港六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
            '老澳' => '/老澳\d{2}\.\d{2}第:(\d+)\s*期开奖结果:\s*([\d\s]+)/'
        ];

        $found = false;
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $found = true;
                $issueNumber = $matches[1];
                $numbers = preg_split('/\s+/', trim($matches[2]));
                $winningNumbers = implode(',', $numbers);

                if (empty($numbers) || empty($issueNumber)) {
                    error_log("[WARNING] Regex matched, but failed to extract issue number or winning numbers for type '{$type}'.");
                    continue;
                }

                $numberDetails = [];
                foreach ($numbers as $number) {
                    $numberDetails[$number] = [
                        'zodiac' => $this->getZodiac($number),
                        'color' => $this->getColor($number)
                    ];
                }
                $numberColorsJson = json_encode($numberDetails, JSON_UNESCAPED_UNICODE);

                error_log("[INFO] Parsed lottery result for '{$type}': Issue {$issueNumber}, Numbers: {$winningNumbers}");

                try {
                    $pdo = $this->getDbConnection();
                    $stmt = $pdo->prepare(
                        "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, number_colors_json, draw_date)
                         VALUES (?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE winning_numbers = VALUES(winning_numbers), number_colors_json = VALUES(number_colors_json), draw_date = NOW()"
                    );
                    $stmt->execute([$type, $issueNumber, $winningNumbers, $numberColorsJson]);
                    error_log("[SUCCESS] Successfully saved lottery result for '{$type}' Issue {$issueNumber} to the database.");
                } catch (\PDOException $e) {
                    error_log("[ERROR] Failed to save lottery result for '{$type}' Issue {$issueNumber}: " . $e->getMessage());
                }
                break;
            }
        }
        if (!$found) {
            error_log("[INFO] No lottery result pattern matched the message text.");
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
