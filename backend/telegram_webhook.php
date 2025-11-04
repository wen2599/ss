<?php
require_once __DIR__ . '/config/load_env.php';
require_once __DIR__ . '/config/database.php';

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$admin_id = getenv('TELEGRAM_ADMIN_ID');
$allowed_channel_id = getenv('ALLOWED_CHANNEL_ID');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit();
}

// 重点：我们只处理来自特定频道的转发消息，并且该消息是由管理员转发的
if (isset($update['message']['forward_from_chat']) && isset($update['message']['from'])) {
    $message = $update['message'];
    $from_id = $message['from']['id'];
    $chat_id = $message['chat']['id']; // 这是机器人和管理员的私聊窗口ID
    $forward_from_chat = $message['forward_from_chat'];
    $forward_channel_id = $forward_from_chat['id'];
    
    // 验证是否是管理员发送的，并且是否从指定频道转发
    if ($from_id == $admin_id && $forward_channel_id == $allowed_channel_id) {
        $lottery_number = trim($message['text']); // 假设开奖号码就是消息文本

        if (!empty($lottery_number)) {
            $mysqli = get_db_connection();
            $stmt = $mysqli->prepare("INSERT INTO lottery_numbers (number, source_channel_id) VALUES (?, ?)");
            $stmt->bind_param("ss", $lottery_number, $forward_channel_id);
            
            if ($stmt->execute()) {
                // (可选) 向管理员回复确认消息
                $reply_text = "成功记录开奖号码：" . $lottery_number;
                file_get_contents("https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($reply_text));
            }
            $stmt->close();
            $mysqli->close();
        }
    }
}