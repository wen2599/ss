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
        $this->channelId = $_ENV['TELEGRAM_CHANNEL_ID'] ?? null;

        if (!$this->botToken || !$this->channelId) {
            error_log('Telegram Bot Token or Channel ID is not configured in .env file.');
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

        // Simple command routing.
        switch ($text) {
            case '/start':
                $this->handleStartCommand($chatId);
                break;
            case '/lottery':
                $this->handleLotteryCommand(); // Will post to the configured channel.
                break;
            // You can add more commands here.
        }
    }

    /**
     * Sends a message to a specific Telegram chat.
     *
     * @param string $chatId The ID of the chat to send the message to.
     * @param string $text The message text.
     * @param string $parseMode Optional. 'HTML' or 'MarkdownV2'.
     */
    private function sendMessage(string $chatId, string $text, string $parseMode = 'HTML')
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

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'ignore_errors' => true // Helps in debugging by not throwing fatal errors on HTTP failures.
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            error_log("Failed to send message to Telegram API.");
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
        $welcomeMessage .= "您可以使用以下命令:\n";
        $welcomeMessage .= "- <code>/lottery</code> - 获取最新的开奖结果并发布到指定频道。\n\n";
        $welcomeMessage .= "机器人会自动将结果发送到预设的频道。";
        
        $this->sendMessage($chatId, $welcomeMessage, 'HTML');
    }

    /**
     * Handles the /lottery command by fetching results and posting to the channel.
     */
    private function handleLotteryCommand()
    {
        if (!$this->channelId) {
            error_log('Cannot handle /lottery, TELEGRAM_CHANNEL_ID is not set.');
            return;
        }
        
        // Get the formatted lottery results string.
        $resultsMessage = LotteryController::getLatestLotteryResultsFormatted();
        
        // Send the message to the configured channel.
        $this->sendMessage($this->channelId, $resultsMessage, 'HTML');
    }
}
