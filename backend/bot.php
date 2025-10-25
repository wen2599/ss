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
    error_log("Bot token or secret token is not configured.");
    exit("Bot token or secret token is not configured.");
}

// Authenticate the request from Telegram
$client_secret_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($client_secret_token !== $secret_token) {
    http_response_code(403);
    // CRITICAL: Log this error. This is the most likely reason for an unresponsive bot.
    error_log("Secret Token Mismatch! Expected: '{$secret_token}', but got: '{$client_secret_token}'. Check your webhook settings.");
    exit("Forbidden: Secret token mismatch.");
}

// --- Main Logic ---
$update = json_decode(file_get_contents('php://input'), true);

// Exit if the update is empty or invalid
if (!$update) {
    http_response_code(200);
    exit("OK: No valid update received.");
}

// 1. Handle Channel Post (for automatic lottery data saving)
if (isset($update['channel_post']['text'])) {
    $message_text = $update['channel_post']['text'];
    $parsed_data = parse_lottery_message($message_text);
    
    if ($parsed_data) {
        save_lottery_draw($parsed_data);
    }
    http_response_code(200);
    exit("OK: Channel post processed.");
}

// 2. Handle Private Message (for admin commands)
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_text = $update['message']['text'];

    // If admin ID is set, and the message is from a non-admin, send a polite rejection.
    if (!empty($admin_id) && $chat_id != $admin_id) {
        send_telegram_message($chat_id, "您好！这是一个私人机器人，感谢您的关注。");
        http_response_code(200);
        exit("OK: Message sent to non-admin.");
    }
    
    // At this point, the user is the admin. Process their commands.
    if (strpos($message_text, '/') === 0) {
        $command_parts = explode(' ', $message_text, 3);
        $command = $command_parts[0];

        switch ($command) {
            case '/start':
            case '/help':
                handle_help_command($chat_id);
                break;
            
            case '/stats':
                handle_stats_command($chat_id);
                break;

            case '/latest':
                handle_latest_command($chat_id);
                break;
            
            case '/add':
                handle_add_command($chat_id, $command_parts);
                break;

            default:
                send_telegram_message($chat_id, "未知命令: {$command}。 输入 /help 查看可用命令。");
                break;
        }
    } else {
         // Respond if the admin sends a text message that is not a command.
         send_telegram_message($chat_id, "您好, 管理员。请输入一个命令来开始，例如 /help");
    }
    http_response_code(200);
    exit("OK: Admin command processed.");
}

// 3. Handle other update types
error_log("Unhandled update type: " . json_encode($update));
http_response_code(200);
exit("OK: Unhandled update type received.");


// --- Command Handler Functions ---

/**
 * Handles the /help and /start command.
 */
function handle_help_command($chat_id) {
    $reply_text = "您好, 管理员！可用的命令有:\n\n" .
                  "/help - 显示此帮助信息\n" .
                  "/stats - 查看系统统计数据\n" .
                  "/latest - 查询最新一条开奖记录\n" .
                  "/add [期号] [号码] - 手动添加开奖记录\n" .
                  "  (例如: /add 2023-01-01-001 01,02,03,04,05)";
    send_telegram_message($chat_id, $reply_text);
}

/**
 * Handles the /stats command.
 */
function handle_stats_command($chat_id) {
    $stats = get_system_stats();
    $reply_text = "📊 系统统计数据:\n" .
                  "  - 注册用户数: {$stats['users']}\n" .
                  "  - 已保存邮件数: {$stats['emails']}\n" .
                  "  - 开奖记录数: {$stats['lottery_draws']}";
    send_telegram_message($chat_id, $reply_text);
}

/**
 * Handles the /latest command.
 */
function handle_latest_command($chat_id) {
    global $db_connection;
    $query = "SELECT draw_date, draw_period, numbers, created_at FROM lottery_draws ORDER BY id DESC LIMIT 1";
    
    if ($result = $db_connection->query($query)) {
        if ($row = $result->fetch_assoc()) {
            $reply_text = "🔍 最新开奖记录:\n" .
                          "  - 日期: {$row['draw_date']}\n" .
                          "  - 期号: {$row['draw_period']}\n" .
                          "  - 号码: {$row['numbers']}\n" .
                          "  - 记录时间: {$row['created_at']}";
        } else {
            $reply_text = "数据库中暂无开奖记录。";
        }
        $result->free();
    } else {
        $reply_text = "查询最新记录时出错。";
        error_log("DB Error in /latest: " . $db_connection->error);
    }
    
    send_telegram_message($chat_id, $reply_text);
}

/**
 * Handles the /add command.
 */
function handle_add_command($chat_id, $command_parts) {
    if (count($command_parts) < 3) {
        send_telegram_message($chat_id, "格式错误。请使用: /add [期号] [号码]\n例如: /add 2023-01-01-001 01,02,03,04,05");
        return;
    }

    $period = $command_parts[1];
    $numbers = $command_parts[2];
    
    // Basic validation
    if (!preg_match('/^\d{4}-\d{2}-\d{2}-\d{3,4}$/', $period) && !preg_match('/^\d+$/', $period)) {
        send_telegram_message($chat_id, "期号格式似乎不正确。应类似于 '2023-01-01-001' 或一串数字。");
        return;
    }
    if (!preg_match('/^(\d{1,2},)+\d{1,2}$/', $numbers)) {
        send_telegram_message($chat_id, "号码格式似乎不正确。应为以逗号分隔的数字，例如 '01,02,03'");
        return;
    }

    $data = [
        'draw_date' => date('Y-m-d'), // Use current date for manual entries
        'draw_period' => $period,
        'numbers' => $numbers
    ];

    if (save_lottery_draw($data)) {
        send_telegram_message($chat_id, "✅ 记录已成功添加:\n  - 日期: {$data['draw_date']}\n  - 期号: {$data['draw_period']}\n  - 号码: {$data['numbers']}");
    } else {
        send_telegram_message($chat_id, "❌ 添加记录失败。可能是数据库错误或该期号已存在。");
        // The error is already logged inside save_lottery_draw()
    }
}


// --- Core Helper Functions ---

/**
 * Sends a message to a specified Telegram chat.
 */
function send_telegram_message($chat_id, $text) {
    global $bot_token;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'ignore_errors' => true
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Log failures from Telegram API
    if ($result === FALSE) {
        error_log("Failed to send message to chat_id: {$chat_id}");
    }
}

/**
 * Retrieves system statistics from the database.
 */
function get_system_stats() {
    global $db_connection;
    $stats = ['users' => 0, 'emails' => 0, 'lottery_draws' => 0];

    // Get user count
    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM users")) {
        $stats['users'] = $result->fetch_assoc()['count'] ?? 0;
        $result->free();
    }
    // Get email count
    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM emails")) {
        $stats['emails'] = $result->fetch_assoc()['count'] ?? 0;
        $result->free();
    }
    // Get lottery draws count
    if ($result = $db_connection->query("SELECT COUNT(*) as count FROM lottery_draws")) {
        $stats['lottery_draws'] = $result->fetch_assoc()['count'] ?? 0;
        $result->free();
    }
    
    return $stats;
}

/**
 * Parses lottery information from a channel message.
 */
function parse_lottery_message($text) {
    $data = [];
    // Example: 2024-03-15 第 240315050 期
    if (preg_match('/(\d{4}-\d{2}-\d{2}) 第 (\w+) 期/', $text, $matches)) {
        $data['draw_date'] = $matches[1];
        $data['draw_period'] = $matches[2];
    } else { return null; }

    // Example: 开奖号码: 02,08,03,05,06
    if (preg_match('/开奖号码: ([\d,]+)/', $text, $matches)) {
        $data['numbers'] = str_replace(' ', '', $matches[1]);
    } else { return null; }
    
    return $data;
}

/**
 * Saves or updates a lottery draw record in the database.
 * Returns true on success, false on failure.
 */
function save_lottery_draw($data) {
    global $db_connection;
    $stmt = $db_connection->prepare("INSERT INTO lottery_draws (draw_date, draw_period, numbers) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers = VALUES(numbers)");
    if (!$stmt) {
        error_log("DB Prepare Error in save_lottery_draw: " . $db_connection->error);
        return false;
    }
    $stmt->bind_param("sss", $data['draw_date'], $data['draw_period'], $data['numbers']);
    $success = $stmt->execute();
    if(!$success) {
        error_log("DB Execute Error in save_lottery_draw: " . $stmt->error);
    }
    $stmt->close();
    return $success;
}
