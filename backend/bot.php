<?php
// backend/bot.php

// --- Bootstrap aplication ---
require_once __DIR__ . '/bootstrap.php';

// --- Configuration & Security ---
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$secret_token = getenv('TELEGRAM_SECRET_TOKEN');
$admin_id = getenv('TELEGRAM_ADMIN_ID');

if (!$bot_token || !$secret_token) {
    http_response_code(500);
    echo "Bot token or secret token is not configured.";
    exit;
}

// Check if the request is from Telegram
$client_secret_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($client_secret_token !== $secret_token) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
}

// --- Main Logic ---
$update = json_decode(file_get_contents('php://input'), true);

// 1. 处理频道消息 (用于自动保存开奖记录)
if (isset($update['channel_post']['text'])) {
    $message_text = $update['channel_post']['text'];
    $parsed_data = parse_lottery_message($message_text);
    
    if ($parsed_data) {
        save_lottery_draw($parsed_data);
    }
    // 频道消息不回复，静默处理
}

// 2. 处理私人消息 (用于管理员命令)
else if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_text = $update['message']['text'];

    // 检查是否是管理员
    if (!empty($admin_id) && $chat_id == $admin_id) {
        // 是管理员，解析命令
        if (strpos($message_text, '/') === 0) {
            $command_parts = explode(' ', $message_text, 2);
            $command = $command_parts[0];

            switch ($command) {
                case '/start':
                case '/help':
                    $reply_text = "您好, 管理员！\n可用的命令有:\n/stats - 查看系统统计数据";
                    send_telegram_message($chat_id, $reply_text);
                    break;
                
                case '/stats':
                    $stats = get_system_stats();
                    $reply_text = "系统统计数据:\n- 注册用户数: {$stats['users']}\n- 已保存邮件数: {$stats['emails']}";
                    send_telegram_message($chat_id, $reply_text);
                    break;

                default:
                    send_telegram_message($chat_id, "未知命令: {$command}");
                    break;
            }
        } else {
             send_telegram_message($chat_id, "您好, 管理员。请输入一个命令来开始，例如 /help");
        }
    } else {
        // 不是管理员，发送友好提示
        send_telegram_message($chat_id, "您好！这是一个私人机器人，感谢您的关注。");
    }
}

http_response_code(200); // 响应Telegram以确认接收
echo "OK";

// --- Function Implementations ---

/**
 * 发送消息到指定的Telegram聊天
 * @param int $chat_id 
 * @param string $text 
 */
function send_telegram_message($chat_id, $text) {
    global $bot_token;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true // so we can see error responses
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

/**
 * 从数据库获取系统统计信息
 * @return array
 */
function get_system_stats() {
    global $db_connection;
    $stats = ['users' => 0, 'emails' => 0];

    // 获取用户数
    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM users")) {
        $stats['users'] = $result->fetch_assoc()['count'];
        $result->free();
    }

    // 获取邮件数
    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM emails")) {
        $stats['emails'] = $result->fetch_assoc()['count'];
        $result->free();
    }
    
    return $stats;
}

/**
 * 解析彩票开奖信息
 * @param string $text
 * @return array|null
 */
function parse_lottery_message($text) {
    $data = [];
    if (preg_match('/(\d{4}-\d{2}-\d{2}) 第 (\w+) 期/', $text, $matches)) {
        $data['draw_date'] = $matches[1];
        $data['draw_period'] = $matches[2];
    } else { return null; }

    if (preg_match('/开奖号码: ([\d,]+)/', $text, $matches)) {
        $data['numbers'] = $matches[1];
    } else { return null; }
    
    return $data;
}

/**
 * 保存彩票开奖记录到数据库
 * @param array $data 
 */
function save_lottery_draw($data) {
    global $db_connection;
    $stmt = $db_connection->prepare("INSERT INTO lottery_draws (draw_date, draw_period, numbers) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers = VALUES(numbers)");
    $stmt->bind_param("sss", $data['draw_date'], $data['draw_period'], $data['numbers']);
    $stmt->execute();
    $stmt->close();
}

