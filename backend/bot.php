<?php
require_once 'config.php';

class TelegramBot {
    private $botToken;
    private $channelId;
    private $apiKey;
    private $db;
    private $adminId;

    public function __construct() {
        $this->botToken = Config::get('BOT_TOKEN');
        $this->channelId = Config::get('CHANNEL_ID');
        $this->apiKey = Config::get('API_KEY');
        $this->adminId = Config::get('TELEGRAM_ADMIN_ID');
        $this->db = new Database();
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
        error_log("=========================");

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        $keyboard = [
            'keyboard' => [
                ['获取最新双色球', '获取最新大乐透'],
                ['帮助']
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];

        switch ($text) {
            case '/start':
                $this->sendMessage($chatId, '欢迎使用！请选择一个操作：', $keyboard);
                break;
            case '获取最新双色球':
                $response = $this->getLatestResult('双色球');
                $this->sendMessage($chatId, $response, $keyboard);
                break;
            case '获取最新大乐透':
                $response = $this->getLatestResult('大乐透');
                $this->sendMessage($chatId, $response, $keyboard);
                break;
            case '帮助':
                $helpText = "这是一个彩票结果查询机器人。\n\n- 我会自动监控指定频道，保存最新的开奖结果。\n- 您可以通过下方的键盘按钮查询已保存的最新结果。";
                $this->sendMessage($chatId, $helpText, $keyboard);
                break;
            default:
                $this->sendMessage($chatId, '抱歉，我不理解您的指令。请使用下方的键盘按钮。', $keyboard);
                break;
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

            // rowCount() returns 1 for a new INSERT, 2 for an UPDATE.
            if ($stmt->rowCount() === 1 && $this->adminId) {
                $message = "✅ New lottery result saved:\nType: {$lotteryType}\nNumber: {$lotteryNumber}\nDate: {$drawDate}";
                $this->sendMessage($this->adminId, $message);
            }

            error_log("Lottery result saved: {$lotteryType} - {$lotteryNumber} for date {$drawDate}");

        } catch (Exception $e) {
            error_log("Error saving lottery result: " . $e->getMessage());
        }
    }

    private function sendMessage($chatId, $text, $reply_markup = null) {
        $apiUrl = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];
        if ($reply_markup) {
            $params['reply_markup'] = json_encode($reply_markup);
        }
        $requestUrl = $apiUrl . '?' . http_build_query($params);

        // Use a stream context to handle potential errors gracefully
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $response = file_get_contents($requestUrl, false, $context);

        $responseData = json_decode($response, true);
        if (!$responseData || !$responseData['ok']) {
            error_log("Failed to send message: " . $response);
        }
    }

    private function getLatestResult($lotteryType) {
        try {
            $pdo = $this->db->getConnection();
            $sql = "SELECT lottery_number, draw_date FROM lottery_results WHERE lottery_type = :type ORDER BY draw_date DESC, id DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':type' => $lotteryType]);
            $result = $stmt->fetch();

            if ($result) {
                return "最新一期 {$lotteryType} 结果:\n日期: {$result['draw_date']}\n号码: {$result['lottery_number']}";
            } else {
                return "抱歉，暂未找到 {$lotteryType} 的开奖结果。";
            }
        } catch (Exception $e) {
            error_log("Error fetching latest result: " . $e->getMessage());
            return "查询数据时出错，请稍后再试。";
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
