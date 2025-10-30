<?php
// 文件名: telegram_webhook.php
// 路径: 项目根目录
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/helpers.php';

// 安全性检查
$secret_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($secret_token !== TELEGRAM_WEBHOOK_SECRET) {
    http_response_code(403);
    error_log('Invalid Telegram webhook access attempt.');
    exit('Forbidden');
}

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) { exit(); }

$message = $update['message'] ?? null;
$chat_id = $message['chat']['id'] ?? null;
$user_id = $message['from']['id'] ?? null;
$text = $message['text'] ?? '';

// 只处理来自管理员的消息
if (!$chat_id || !is_admin($user_id)) {
    error_log("Unauthorized message from user ID: $user_id");
    exit();
}

// 命令解析
$parts = explode(' ', $text);
$command = $parts[0] ?? '';

try {
    switch ($command) {
        case '/start':
            send_telegram_message($chat_id, "您好，管理员！我是您的彩票助手。");
            break;
        case '/add':
            // 示例: /add 20240523 01,02,03,04,05,06 07
            if (count($parts) < 4) {
                send_telegram_message($chat_id, "格式错误。用法: /add <期号> <平码,逗号分隔> <特码>");
                break;
            }
            $issue_number = $parts[1];
            $winning_numbers = $parts[2];
            $special_number = $parts[3];
            $draw_date = date("Y-m-d"); // Or parse from issue number
            
            $db = get_db_connection();
            $stmt = $db->prepare("INSERT INTO lottery_results (issue_number, winning_numbers, special_number, draw_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$issue_number, $winning_numbers, $special_number, $draw_date]);
            
            send_telegram_message($chat_id, "✅ 期号 {$issue_number} 的开奖结果已成功添加。");
            break;
        // 在这里添加更多管理员命令, 例如 /authorize_email, /set_gemini_key 等
        default:
            send_telegram_message($chat_id, "未知命令: {$command}");
            break;
    }
} catch (Exception $e) {
    error_log("Telegram Bot Error: " . $e->getMessage());
    send_telegram_message($chat_id, "❌ 处理命令时发生错误。");
}

function send_telegram_message($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $payload = ['chat_id' => $chat_id, 'text' => $text];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_exec($ch);
    curl_close($ch);
}