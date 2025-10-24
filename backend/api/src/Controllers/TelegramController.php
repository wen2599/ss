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
    }

    public function handleWebhook()
    {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        if (isset($update['channel_post']['text'])) {
            $message = $update['channel_post'];
            $chatId = $message['chat']['id'];
            $text = $message['text'];

            if ((string)$chatId === $this->channelId) {
                $this->_parseAndSaveLotteryResult($text);
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
                $numberColorsJson = json_encode($numberDetails);

                try {
                    $pdo = $this->getDbConnection();
                    $stmt = $pdo->prepare(
                        "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, number_colors_json, draw_date)
                         VALUES (?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE winning_numbers = VALUES(winning_numbers), number_colors_json = VALUES(number_colors_json), draw_date = NOW()"
                    );
                    $stmt->execute([$type, $issueNumber, $winningNumbers, $numberColorsJson]);
                } catch (\PDOException $e) {
                    error_log('Failed to save lottery result: ' . $e->getMessage());
                }
                break;
            }
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
