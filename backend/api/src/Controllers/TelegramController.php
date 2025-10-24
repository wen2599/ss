<?php
namespace App\Controllers;

// Since we are now using a controller, we need to make sure the bootstrap file is included.
// It should be included by the entry point script (e.g., webhook.php) that uses this controller.

class TelegramController {

    private $botToken;
    private $channelId;

    public function __construct()
    {
        // Fetch the bot token and channel ID from environment variables.
        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        // Correctly read the channel ID from the LOTTERY_CHANNEL_ID environment variable.
        $this->channelId = $_ENV['LOTTERY_CHANNEL_ID'] ?? null;

        if (!$this->botToken || !$this->channelId) {
            error_log('Telegram Bot Token or Lottery Channel ID is not configured correctly in .env file.');
            // We don't exit here, to avoid exposing internal errors via HTTP responses.
        }
    }

    /**
     * Handles incoming webhook requests from Telegram.
     */
    public function handleWebhook()
    {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        if (!isset($update['message']['text'])) {
            return; // Not a text message, ignore.
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'];

        // Command and text routing.
        switch ($text) {
            case '/start':
                $this->handleStartCommand($chatId);
                break;
            
            // Also handle the text from the keyboard button.
            case '获取最新开奖结果':
            case '/lottery':
                // Notify the user that the request is being processed.
                $this->sendMessage($chatId, "正在获取最新开奖结果并发送到频道...");
                $this->handleLotteryCommand($chatId);
                break;

            // You can add more commands here.
            default:
                // Send a default message if the command is not recognized.
                $this->sendMessage($chatId, "抱歉，我无法识别该命令。请使用下方的菜单或输入 /start。", 'HTML', $this->getMainMenuKeyboard());
                break;
        }
    }

    /**
     * Creates the main reply keyboard markup.
     * @return array The reply keyboard markup.
     */
    private function getMainMenuKeyboard()
    {
        return [
            'keyboard' => [
                ['获取最新开奖结果'] // First row with one button
            ],
            'resize_keyboard' => true, // Make the keyboard smaller
            'one_time_keyboard' => false // Keep the keyboard open
        ];
    }

    /**
     * Sends a message to a specific Telegram chat.
     *
     * @param string $chatId The ID of the chat to send the message to.
     * @param string $text The message text.
     * @param string|null $parseMode Optional. 'HTML' or 'MarkdownV2'.
     * @param array|null $replyMarkup Optional. A ReplyKeyboardMarkup array.
     */
    private function sendMessage(string $chatId, string $text, string $parseMode = 'HTML', ?array $replyMarkup = null)
    {
        if (!$this->botToken) {
            error_log('Attempted to send message but Bot Token is missing.');
            return;
        }

        $url = "https://api.telegram.org/bot" . $this->botToken . "/sendMessage";
        
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];

        // Add the keyboard to the payload if it's provided.
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
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
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result, true);

        if ($result === FALSE || (isset($response['ok']) && !$response['ok'])) {
            error_log("Failed to send message to Telegram API. Response: " . $result);
        }
    }

    /**
     * Handles the /start command.
     *
     * @param string $chatId The chat ID to send the welcome message to.
     */
    private function handleStartCommand(string $chatId)
    {
        $welcomeMessage = "<b>👋 欢迎使用开奖结果机器人!</b>\n\n";
        $welcomeMessage .= "点击下方的按钮来获取最新的开奖结果，结果会自动发布到指定频道。";
        
        // Send the welcome message along with the main menu keyboard.
        $this->sendMessage($chatId, $welcomeMessage, 'HTML', $this->getMainMenuKeyboard());
    }

    /**
     * Handles the /lottery command by fetching results and posting to the channel.
     * @param string $chatId The chat ID of the user who initiated the command.
     */
    private function handleLotteryCommand(string $chatId)
    {
        if (!$this->channelId) {
            error_log('Cannot handle /lottery, LOTTERY_CHANNEL_ID is not set.');
            // Inform the user about the configuration error.
            $this->sendMessage($chatId, "抱歉，机器人后台配置不正确，无法找到目标频道。请联系管理员。");
            return;
        }
        
        // Get the formatted lottery results string.
        $resultsMessage = LotteryController::getLatestLotteryResultsFormatted();
        
        // Send the message to the configured channel.
        $this->sendMessage($this->channelId, $resultsMessage, 'HTML');

        // Confirm to the user that the message has been sent.
        $this->sendMessage($chatId, "✅ 最新结果已成功发送到频道！");
    }
}
