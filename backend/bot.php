<?php
require_once 'config.php';

class TelegramBot {
    private $botToken;
    private $channelUsername;
    private $db;
    
    public function __construct() {
        $this->botToken = Config::get('BOT_TOKEN');
        $this->channelUsername = Config::get('CHANNEL_USERNAME');
        $this->db = new Database();
    }
    
    public function processUpdate($update) {
        if (!isset($update['message']) && !isset($update['channel_post'])) {
            return;
        }
        
        $message = isset($update['message']) ? $update['message'] : $update['channel_post'];
        
        // 检查消息是否来自目标频道
        $chat = $message['chat'];
        if ($chat['username'] !== str_replace('@', '', $this->channelUsername)) {
            return;
        }
        
        if (isset($message['text'])) {
            $this->processTextMessage($message);
        }
    }
    
    private function processTextMessage($message) {
        $text = $message['text'];
        
        // 解析开奖号码（这里需要根据实际的格式调整正则表达式）
        if (preg_match('/(双色球|大乐透).*?(\d{2}\s+\d{2}\s+\d{2}\s+\d{2}\s+\d{2}\s+\d{2}\+\d{2})/', $text, $matches)) {
            $lotteryType = $matches[1];
            $lotteryNumber = $matches[2];
            
            $this->saveLotteryResult($lotteryType, $lotteryNumber);
        }
    }
    
    private function saveLotteryResult($lotteryType, $lotteryNumber) {
        try {
            $pdo = $this->db->getConnection();
            
            $sql = "INSERT INTO lottery_results (lottery_number, lottery_type, draw_date) 
                    VALUES (:number, :type, CURDATE())
                    ON DUPLICATE KEY UPDATE lottery_number = :number, updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':number' => $lotteryNumber,
                ':type' => $lotteryType
            ]);
            
            error_log("Lottery result saved: {$lotteryType} - {$lotteryNumber}");
            
        } catch (Exception $e) {
            error_log("Error saving lottery result: " . $e->getMessage());
        }
    }
}

// 处理Webhook请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);
    
    $bot = new TelegramBot();
    $bot->processUpdate($update);
    
    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(405);
    echo 'Method Not Allowed';
}
?>