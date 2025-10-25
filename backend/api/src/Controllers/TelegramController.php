<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\TelegramService;
use App\Controllers\LotteryController;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Throwable;

class TelegramController extends BaseController
{
    private TelegramService $telegramService;
    private PDO $pdo;
    private ?LoggerInterface $logger;
    private ?string $channelId;
    private ?string $adminId;

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

    public function __construct(
        TelegramService $telegramService,
        PDO $pdo,
        ?LoggerInterface $logger = null,
        ?string $channelId = null,
        ?string $adminId = null
    ) {
        $this->telegramService = $telegramService;
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->channelId = $channelId;
        $this->adminId = $adminId;
    }

    /**
     * Handles incoming Telegram webhook updates.
     * @param array $update The decoded JSON update from Telegram.
     */
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

    /**
     * Handles Telegram commands (e.g., /start, /lottery).
     * @param string $chatId The ID of the chat where the command originated.
     * @param string $commandText The full command text.
     */
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

    /**
     * Handles the /start command.
     * @param string $chatId The ID of the chat.
     */
    private function handleStartCommand(string $chatId): void
    {
        $welcomeMessage = "欢迎使用开奖中心Bot！\n\n"
            . "我可以为您提供最新的开奖结果。\n"
            . "请使用以下命令：\n"
            . "/lottery - 获取最新开奖结果\n"
            . "/start - 查看此欢迎信息";
        $this->telegramService->sendMessage($chatId, $welcomeMessage);
    }

    /**
     * Handles the /lottery command, fetching and sending latest lottery results.
     * @param string $chatId The ID of the chat.
     */
    private function handleLotteryCommand(string $chatId): void
    {
        try {
            // LotteryController automatically gets PDO from BaseController
            $lotteryController = new LotteryController();
            $results = $lotteryController->fetchLatestResultsData();

            if (empty($results)) {
                $this->telegramService->sendMessage($chatId, "抱歉，目前没有最新的开奖结果。");
                return;
            }

            $formattedResults = $this->formatLotteryResultsForTelegram($results);
            $this->telegramService->sendMessage($chatId, $formattedResults, 'MarkdownV2');

        } catch (PDOException $e) {
            $this->logError('Database error during /lottery command execution.', ['exception' => $e]);
            $this->telegramService->sendMessage($chatId, "抱歉，获取开奖结果时发生数据库错误，请稍后再试。");
            $this->notifyAdmin("Bot在处理 /lottery 命令时发生数据库错误: " . $e->getMessage(), $e);
        } catch (Throwable $e) {
            $this->logError('Unexpected error during /lottery command execution.', ['exception' => $e]);
            $this->telegramService->sendMessage($chatId, "抱歉，获取开奖结果时发生未知错误，请稍后再试。");
            $this->notifyAdmin("Bot在处理 /lottery 命令时发生未知错误: " . $e->getMessage(), $e);
        }
    }

    /**
     * Formats lottery results into a Telegram MarkdownV2 compatible string.
     * @param array $results An array of lottery results.
     * @return string Formatted string for Telegram.
     */
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

    /**
     * Parses an incoming message text to extract and save lottery results.
     * Only processes messages from the configured channel ID.
     * @param string $text The message text to parse.
     */
    private function parseAndSaveLotteryResult(string $text): void
    {
        // The PDO connection is now injected via the constructor.
        
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
                    if ($numStr === '00') continue; // Skip if number is 0 after padding
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
                    $this->notifyAdmin("Bot: 已保存 \'{$type}\' 第 \'{$issueNumber}\' 期开奖结果。", null);

                } catch (PDOException $e) {
                    $this->logError('Failed to save lottery result to database.', ['exception' => $e]);
                    $this->notifyAdmin('数据库错误：保存开奖结果失败: ' . $e->getMessage(), $e);
                }
                return; // Stop after first match
            }
        }
         $this->logInfo('No lottery result pattern matched.', ['text' => $text]);
    }

    // --- Utility and Helper Methods ---

    /**
     * Determines the zodiac sign for a given number.
     * @param string $number The number (e.g., '01').
     * @return string|null The corresponding zodiac sign or null if not found.
     */
    private function getZodiac(string $number): ?string
    {
        foreach (self::ZODIAC_MAP as $zodiac => $numbers) {
            if (in_array($number, $numbers, true)) {
                return $zodiac;
            }
        }
        return null;
    }

    /**
     * Determines the color for a given number.
     * @param string $number The number (e.g., '01').
     * @return string|null The corresponding color or null if not found.
     */
    private function getColor(string $number): ?string
    {
        foreach (self::COLOR_MAP as $color => $numbers) {
            if (in_array($number, $numbers, true)) {
                return $color;
            }
        }
        return null;
    }

    /**
     * Escapes characters in a string for Telegram MarkdownV2.
     * @param string $text The text to escape.
     * @return string The escaped text.
     */
    private function escapeMarkdownV2(string $text): string
    {
        // Characters that need to be escaped in MarkdownV2 if they are not part of formatting
        $escapedChars = [
            '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '.', '!'
        ];
        $replacements = array_map(fn($char) => '\\' . $char, $escapedChars);
        return str_replace($escapedChars, $replacements, $text);
    }

    /**
     * Notifies the admin via Telegram.
     * @param string $message The message to send.
     * @param \Throwable|null $e Optional exception to include details for debugging.
     */
    private function notifyAdmin(string $message, ?Throwable $e = null): void
    {
        if ($this->adminId) {
            if ($e && ($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                $message .= sprintf(
                    "\n\n*Debug Info:*\nError: `%s`\nFile: `%s` on line `%d`\nTrace: `...`",
                    $this->escapeMarkdownV2($e->getMessage()),
                    $this->escapeMarkdownV2($e->getFile()),
                    $e->getLine()
                ); 
                 // Simplified trace to avoid excessively long messages in Telegram
            }
             $this->telegramService->sendMessage($this->adminId, $message, 'MarkdownV2');
        }
    }

    /**
     * Logs an informational message.
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
            // Optionally add full trace to log, but keep it concise for Telegram notifications
            // $logMessage .= "\nStack Trace:\n" . $e->getTraceAsString();
        }
        $logMessage .= (empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
        error_log($logMessage);
    }
    
    /**
     * Handles fatal errors encountered during webhook processing.
     * Notifies the admin and logs the error.
     * @param Throwable $e The thrown exception or error.
     */
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
        $this->notifyAdmin($errorMessage, $e);
    }
}
