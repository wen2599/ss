<?php
require_once 'config.php';

class TelegramBot {
    private $botToken;
    private $channelId;
    private $apiKey;
    private $db;

    private $webhookSecret;

    public function __construct() {
        $this->botToken = Config::get('BOT_TOKEN');
        $this->channelId = Config::get('CHANNEL_ID');
        $this->apiKey = Config::get('API_KEY');
        $this->webhookSecret = Config::get('TELEGRAM_WEBHOOK_SECRET');
        $this->db = new Database();
    }

    private function validateWebhookSecret() {
        $secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if ($this->webhookSecret && $secretToken !== $this->webhookSecret) {
            http_response_code(403);
            error_log('Webhook secret validation failed.');
            echo 'Forbidden';
            exit;
        }
    }

    public function handleRequest() {
        // Log request details for debugging
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        $input = file_get_contents('php://input');

        error_log("===== New Bot Request =====");
        error_log("Headers: " . json_encode($headers, JSON_PRETTY_PRINT));
        error_log("Input Body: " . $input);
        error_log("Loaded Webhook Secret: " . ($this->webhookSecret ? 'SET' : 'NOT SET'));
        error_log("=========================");

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateWebhookSecret();

            $update = json_decode($input, true);
            if ($update) {
                $this->processUpdate($update);
            }
            http_response_code(200);
            echo 'OK';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->healthCheck();
        } else {
            http_response_code(405);
            echo 'Method Not Allowed';
        }
    }

    private function healthCheck() {
        header('Content-Type: application/json');
        $dbStatus = 'disconnected';
        try {
            $this->db->getConnection();
            $dbStatus = 'connected';
        } catch (Exception $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        echo json_encode([
            'status' => 'running',
            'bot_token' => $this->botToken && $this->botToken !== 'YOUR_TELEGRAM_BOT_TOKEN' ? 'set' : 'not set',
            'channel_id' => $this->channelId && $this->channelId !== 'YOUR_CHANNEL_ID' ? 'set' : 'not set',
            'database_status' => $dbStatus,
            'timestamp' => date('c')
        ]);
    }

    public function processUpdate($update) {
        if (isset($update['channel_post'])) {
            $this->handleChannelPost($update['channel_post']);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }
    }

    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if ($text === '/start') {
            $this->sendMessage($chatId, 'Welcome! This bot is active and ready to process channel posts.');
        } else {
            // Optionally, handle other direct commands here
            $this->sendMessage($chatId, 'I am a channel bot and do not have other commands for direct messages.');
        }
    }

    private function handleChannelPost($message) {
        $chatId = $message['chat']['id'] ?? null;
        if ($chatId != $this->channelId) {
            error_log("Channel post from ignored chat ID: {$chatId}");
            return;
        }

        if (isset($message['text'])) {
            $this->processTextMessage($message);
        }
    }

    private function processTextMessage($message) {
        $text = $message['text'];
        $messageDate = $message['date'];

        $patterns = [
            '双色球' => '/(?:红球|蓝球|号码|)\s*[:：]?\s*([\d\s,\uff0c]+?)\s*\+\s*([\d]+)/u',
            '大乐透' => '/(?:前区|后区|号码|)\s*[:：]?\s*([\d\s,\uff0c]+?)\s*\+\s*([\d\s,\uff0c]+)/u',
        ];

        foreach ($patterns as $type => $pattern) {
            if (strpos($text, $type) !== false && preg_match($pattern, $text, $matches)) {
                $mainNumbers = preg_replace('/[^\d]+/u', ' ', $matches[1]);
                $specialNumbers = preg_replace('/[^\d]+/u', ' ', $matches[2]);
                $lotteryNumber = trim($mainNumbers) . ' + ' . trim($specialNumbers);
                $drawDate = date('Y-m-d', $messageDate);

                $this->saveLotteryResult($type, $lotteryNumber, $drawDate);
                return; 
            }
        }
    }

    private function saveLotteryResult($lotteryType, $lotteryNumber, $drawDate) {
        try {
            $pdo = $this->db->getConnection();
            $sql = "INSERT INTO lottery_results (lottery_type, lottery_number, draw_date) VALUES (:type, :number, :date) ON DUPLICATE KEY UPDATE lottery_number = VALUES(lottery_number), updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':type' => $lotteryType,
                ':number' => $lotteryNumber,
                ':date' => $drawDate
            ]);

            error_log("Lottery result saved: {$lotteryType} - {$lotteryNumber} for date {$drawDate}");

        } catch (Exception $e) {
            error_log("Error saving lottery result: " . $e->getMessage());
        }
    }

    private function sendMessage($chatId, $text) {
        $apiUrl = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        $requestUrl = $apiUrl . '?' . http_build_query($params);

        // Use a stream context to handle potential errors gracefully
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $response = file_get_contents($requestUrl, false, $context);

        $responseData = json_decode($response, true);
        if (!$responseData || !$responseData['ok']) {
            error_log("Failed to send message: " . $response);
        }
    }
}

try {
    $bot = new TelegramBot();
    $bot->handleRequest();
} catch (Exception $e) {
    // A 500 error will cause Telegram to retry the webhook request
    http_response_code(500);
    error_log('Bot Initialization Error: ' . $e->getMessage());
    // Respond with a JSON error if possible, otherwise plain text
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Bot Initialization Error: ' . $e->getMessage()
    ]);
}
?>
