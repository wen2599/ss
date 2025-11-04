<?php
// webhook.php - (增强版) 同时处理频道消息和私聊命令

require_once 'db.php';

$env_path = __DIR__ . '/.env';
$env = parse_ini_file($env_path);
if ($env === false) { http_response_code(500); exit; }

// 1. 安全性检查
$expected_secret = $env['TELEGRAM_WEBHOOK_SECRET'];
$received_secret = isset($_GET['secret']) ? $_GET['secret'] : '';
if (empty($expected_secret) || $received_secret !== $expected_secret) {
    http_response_code(403); exit;
}

// 2. 消息处理路由
$update_json = file_get_contents('php://input');
$update = json_decode($update_json);
if (!$update) { http_response_code(200); exit; }

if (isset($update->channel_post) && $update->channel_post->chat->id == $env['TELEGRAM_CHANNEL_ID']) {
    handleChannelPost($update->channel_post);
} elseif (isset($update->message)) {
    handlePrivateMessage($update->message);
} else {
    http_response_code(200);
}

// 3. 处理器函数
function handleChannelPost($channel_post) {
    global $env;
    $message_text = trim($channel_post->text);
    $lottery_type = null; $issue_number = null; $winning_numbers = null;
    $lines = explode("\n", $message_text);
    if (count($lines) >= 3) {
        if (preg_match('/^(.*?)第:(\d+)\s*期开奖结果:$/u', trim($lines[1]), $matches)) {
            $lottery_type = trim($matches[1]);
            $issue_number = trim($matches[2]);
            $potential_numbers = trim($lines[2]);
            if (preg_match('/^[\d\s]+$/', $potential_numbers)) {
                $winning_numbers = preg_replace('/\s+/', ' ', $potential_numbers);
            }
        }
    }
    if ($lottery_type && $issue_number && $winning_numbers) {
        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $lottery_type, $issue_number, $winning_numbers);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    http_response_code(200);
}

function handlePrivateMessage($message) {
    $chat_id = $message->chat->id;
    $text = isset($message->text) ? trim($message->text) : '';
    if ($text === '/start') {
        sendMessage($chat_id, "您好！机器人工作正常。");
    }
    http_response_code(200);
}

function sendMessage($chat_id, $text) {
    global $env;
    $bot_token = $env['TELEGRAM_BOT_TOKEN'];
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $params = ['chat_id' => $chat_id, 'text' => $text];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>