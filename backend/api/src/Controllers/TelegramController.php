<?php
namespace App\Controllers;

use App\Services\TelegramService;
use App\Controllers\LotteryController;
use PDO;
use Throwable;
use Psr\Log\LoggerInterface;

class TelegramController extends BaseController
{
    private TelegramService $telegramService;
    private ?PDO $pdo;
    private ?LoggerInterface $logger;
    private ?string $channelId;
    private ?string $adminId;

    // --- Data Maps ---
    private const ZODIAC_MAP = [
        '鼠' => ['06', '18', '30', '42'], '牛' => ['05', '17', '29', '41'], '虎' => ['04', '16', '28', '40'],
        '兔' => ['03', '15', '27', '39'], '龙' => ['02', '14', '26', '38'], '蛇' => ['01', '13', '25', '37', '49'],
        '马' => ['12', '24', '36', '48'], '羊' => ['11', '23', '35', '47'], '猴' => ['10', '22', '34', '46'],
        '鸡' => ['09', '21', '33', '45'], '狗' => ['08', '20', '32', '44'], '猪' => ['07', '19', '31', '43']
    ];
    private const COLOR_MAP = [
        '红' => ['01', '02', '07', '08', '12', '13', '18', '19', '23', '24', '29', '30', '34', '35', '40', '45', '46'],
        '蓝' => ['03', '04', '09', '10', '14', '15', '20', '25', '26', '31', '36', '37', '41', '42', '47', '48'],
        '绿' => ['05', '06', '11', '16', '17', '21', '22', '27', '28', '32', '33', '38', '39', '43', '44', '49']
    ];

    public function __construct(TelegramService $telegramService, ?PDO $pdo, ?LoggerInterface $logger, ?string $channelId, ?string $adminId)
    {
        $this->telegramService = $telegramService;
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->channelId = $channelId;
        $this->adminId = $adminId;
    }

    public function handleWebhook(array $update): void
    {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        if (empty($botToken) || $botToken === 'YOUR_TELEGRAM_BOT_TOKEN') {
            $this->logError('Webhook received, but TELEGRAM_BOT_TOKEN is not configured. Ignoring request.');
            return;
        }

        try {
            $message = $update['message'] ?? $update['edited_message'] ?? $update['channel_post'] ?? $update['edited_channel_post'] ?? null;
            if (!$message) {
                $this->logInfo('Ignoring non-message update.');
                return;
            }

            $chatId = (string)($message['chat']['id'] ?? '');
            $text = trim($message['text'] ?? '');

            if (empty($chatId)) {
                $this->logInfo('Ignoring message without chat ID.');
                return;
            }

            if ($text === '' && $chatId !== $this->channelId) {
                $this->logInfo('Ignoring empty message from non-channel chat.');
                return;
            }

            // Route the message to the appropriate handler
            if (str_starts_with($text, '/')) {
                $this->handleCommand($chatId, $text);
            } elseif ($chatId === $this->channelId) {
                $this->parseAndSaveLotteryResult($text);
            }

        } catch (Throwable $e) {
            $this->handleFatalError($e);
        }
    }

    private function handleCommand(string $chatId, string $commandText): void
    {
        $parts = explode(' ', $commandText, 2);
        $command = strtolower($parts[0]);

        switch ($command) {
            case '/start':
                $this->handleStartCommand($chatId);
                break;
            case '/lottery':
                $this->handleLotteryCommand($chatId);
                break;
            default:
                $this->telegramService->sendMessage($chatId, "抱歉，我不认识这个命令。请尝试 /start 或 /lottery。");
                break;
        }
    }

    private function handleStartCommand(string $chatId): void
    {
        $welcomeMessage = "欢迎使用开奖中心Bot！\n\n"
            . "我可以为您提供最新的开奖结果。\n"
            . "请使用以下命令：\n"
            . "/lottery - 获取最新开奖结果\n"
            . "/start - 查看此欢迎信息";
        $this->telegramService->sendMessage($chatId, $welcomeMessage);
    }

    private function handleLotteryCommand(string $chatId): void
    {
        if (!$this->pdo) {
            $this->logError('Database connection is not available. Cannot handle /lottery command.');
            $this->telegramService->sendMessage($chatId, "抱歉，服务暂时不可用，无法查询开奖结果。");
            return;
        }

        try {
            $lotteryController = new LotteryController($this->pdo);
            $results = $lotteryController->fetchLatestResultsData();

            if (empty($results)) {
                $this->telegramService->sendMessage($chatId, "抱歉，目前没有最新的开奖结果。");
                return;
            }

            $formattedResults = $this->formatLotteryResultsForTelegram($results);
            $this->telegramService->sendMessage($chatId, $formattedResults, 'MarkdownV2');

        } catch (Throwable $e) {
            $this->logError('Error during /lottery command execution.', ['exception' => $e]);
            $this->telegramService->sendMessage($chatId, "抱歉，获取开奖结果时发生错误，请稍后再试。");
            $this->notifyAdmin("Bot在处理 /lottery 命令时发生错误: " . $e->getMessage());
        }
    }

    private function formatLotteryResultsForTelegram(array $results): string
    {
        $message = "*最新开奖结果*\n\n";
        foreach ($results as $result) {
            $safeType = $this->escapeMarkdownV2($result['lottery_type']);
            $safeIssue = $this->escapeMarkdownV2($result['issue_number']);
            $message .= "*" . $safeType . "* \- 第 " . $safeIssue . " 期\n";
            $message .= "开奖号码: ";
            $numbers = explode(',', $result['winning_numbers']);
            foreach ($numbers as $number) {
                $message .= "`" . $this->escapeMarkdownV2(trim($number)) . "` ";
            }
            $message .= "\n";
            $message .= "开奖日期: " . $this->escapeMarkdownV2(date('Y-m-d H:i:s', strtotime($result['draw_date']))) . "\n\n";
        }
        return $message;
    }

    private function parseAndSaveLotteryResult(string $text): void
    {
        if (!$this->pdo) {
            $this->logError('Database connection is not available. Cannot parse lottery result.');
            return;
        }
        // Regex patterns for different lottery types
        $patterns = [
            '新澳' => '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
            '香港' => '/香港六合彩第:(\d+)期开奖结果:\s*([\d\s]+)/',
            '老澳' => '/老澳\d{2}\.\d{2}第:(\d+)\s*期开奖结果:\s*([\d\s]+)/'
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $issueNumber = $matches[1];
                $numbers = preg_split('/\s+/', trim($matches[2]));
                $winningNumbers = implode(',', array_filter($numbers)); // Filter empty values

                $numberDetails = [];
                foreach ($numbers as $number) {
                    $numStr = str_pad((string)(int)$number, 2, '0', STR_PAD_LEFT);
                    if ($numStr === '00') continue;
                    $numberDetails[$numStr] = [
                        'zodiac' => $this->getZodiac($numStr),
                        'color' => $this->getColor($numStr)
                    ];
                }
                $numberColorsJson = json_encode($numberDetails, JSON_UNESCAPED_UNICODE);

                try {
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, number_colors_json, draw_date)
                         VALUES (?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE winning_numbers = VALUES(winning_numbers), number_colors_json = VALUES(number_colors_json), draw_date = NOW()"
                    );
                    $stmt->execute([$type, $issueNumber, $winningNumbers, $numberColorsJson]);
                    $this->logInfo("Lottery result saved.", ['type' => $type, 'issue' => $issueNumber]);

                } catch (\PDOException $e) {
                    $this->logError('Failed to save lottery result to database.', ['exception' => $e]);
                    $this->notifyAdmin('数据库错误：保存开奖结果失败: ' . $e->getMessage());
                }
                return; // Stop after first match
            }
        }
         $this->logInfo('No lottery result pattern matched.', ['text' => $text]);
    }

    // --- Utility and Helper Methods ---

    private function getZodiac(string $number): ?string
    {
        foreach (self::ZODIAC_MAP as $zodiac => $numbers) {
            if (in_array($number, $numbers, true)) {
                return $zodiac;
            }
        }
        return null;
    }

    private function getColor(string $number): ?string
    {
        foreach (self::COLOR_MAP as $color => $numbers) {
            if (in_array($number, $numbers, true)) {
                return $color;
            }
        }
        return null;
    }

    private function escapeMarkdownV2(string $text): string
    {
        // Escape characters for Telegram MarkdownV2
        return str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            [\'_\', \'*\', \'[\', \'\]\', \'(\', \')\', \'~\', \'`\', \' >\', \'#\', \'+\', \'-\', \'=\', \'|\', \'{\', \'}\', \'\.\', \'!\'],
            $text
        );
    }

    private function notifyAdmin(string $message): void
    {
        if ($this->adminId) {
            $this->telegramService->sendMessage($this->adminId, $message);
        }
    }

    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }

    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
    
    private function handleFatalError(Throwable $e): void
    {
        $this->logError('Fatal Error in Webhook Handler', ['exception' => $e]);
        $errorMessage = sprintf(
            "🚨 *Bot Critical Error* 🚨\n\n"
            . "The bot encountered a fatal error\. It may be unresponsive\.\n\n"
            . "*Error:*\n`%s`\n\n"
            . "*File:*\n`%s` on line `%d`",
            $this->escapeMarkdownV2($e->getMessage()),
            $this->escapeMarkdownV2($e->getFile()),
            $e->getLine()
        );
        $this->notifyAdmin($errorMessage);
    }
}
