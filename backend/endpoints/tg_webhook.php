<?php
// backend/endpoints/tg_webhook.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

// --- Custom Telegram Message Function ---
function send_telegram_message($chat_id, $text, $reply_markup = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($reply_markup) {
        $payload['reply_markup'] = json_encode($reply_markup);
    }
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($payload),
        ],
    ];
    @file_get_contents($url, false, stream_context_create($options)); // Use @ to suppress warnings on failure
}

// --- Lottery Result Parser ---
function parse_lottery_message($text) {
    $patterns = [
        '新澳门六合彩' => '/新澳门六合彩第:(\d+)期开奖结果:\n((?:\d+\s+){6}\d+)\n((?:[\p{Han}]+\s+){6}[\p{Han}]+)\n((?:[\x{1F534}\x{1F7E2}\x{1F535}a-zA-Z]+\s*){7})/u',
        '香港六合彩'   => '/香港六合彩第:(\d+)期开奖结果:\n((?:\d+\s+){6}\d+)\n((?:[\p{Han}]+\s+){6}[\p{Han}]+)\n((?:[\x{1F534}\x{1F7E2}\x{1F535}a-zA-Z]+\s*){7})/u',
        '老澳21.30'  => '/老澳21\.30第:(\d+)\s*期开奖结果:\n((?:\d+\s+){6}\d+)\n((?:[\p{Han}]+\s+){6}[\p{Han}]+)\n((?:[\x{1F534}\x{1F7E2}\x{1F535}a-zA-Z]+\s*){7})/u',
    ];

    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            return [
                'lottery_type' => $type,
                'issue'        => trim($matches[1]),
                'numbers'      => json_encode(preg_split('/\s+/u', trim($matches[2]), -1, PREG_SPLIT_NO_EMPTY)),
                'zodiacs'      => json_encode(preg_split('/\s+/u', trim($matches[3]), -1, PREG_SPLIT_NO_EMPTY)),
                'colors'       => json_encode(preg_split('/\s+/u', trim($matches[4]), -1, PREG_SPLIT_NO_EMPTY)),
            ];
        }
    }
    return null;
}

// --- Database & Command Helpers ---
function get_db_for_status() {
    return @get_db_connection(); 
}

function get_db_or_exit($chat_id, $is_admin_command) {
    $conn = get_db_connection();
    if (!$conn) {
        if ($is_admin_command) send_telegram_message($chat_id, "🚨 *数据库错误:* 连接失败。");
        else error_log("DB connection failed in webhook.");
        exit;
    }
    return $conn;
}

function parse_email_from_command($command_text) {
    $parts = explode(' ', $command_text, 2);
    return filter_var(trim($parts[1] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
}

// --- Main Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$message = $update['message'] ?? $update['channel_post'] ?? null;
if (!$message) exit;

$chat_id = $message['chat']['id'];
$text = trim($message['text']);

// --- Branch 1: Process Lottery Results from Channel ---
if (isset($update['channel_post']) && (string)$chat_id === (string)TELEGRAM_CHANNEL_ID) {
    if (empty(TELEGRAM_CHANNEL_ID)) exit;
    $lottery_data = parse_lottery_message($text);
    if ($lottery_data) {
        $conn = get_db_or_exit($chat_id, false);
        $stmt = $conn->prepare("INSERT INTO lottery_results (lottery_type, issue, numbers, zodiacs, colors) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE numbers=VALUES(numbers), zodiacs=VALUES(zodiacs), colors=VALUES(colors)");
        $stmt->bind_param("sssss", $lottery_data['lottery_type'], $lottery_data['issue'], $lottery_data['numbers'], $lottery_data['zodiacs'], $lottery_data['colors']);
        if ($stmt->execute()) error_log("Success: Parsed and saved {$lottery_data['lottery_type']} issue {$lottery_data['issue']}");
        else error_log("DB Error: Failed to save lottery result: " . $stmt->error);
        $stmt->close();
        $conn->close();
    }
    exit;
}

// --- Branch 2: Handle Admin Commands ---
$user_id = $message['from']['id'] ?? null;

// *** DEBUGGING STEP ***: Check for Admin ID mismatch
if ((string)$user_id !== (string)TELEGRAM_ADMIN_ID) {
    if ($chat_id) { // Ensure we have a chat_id to respond to
        $admin_id_defined = defined('TELEGRAM_ADMIN_ID') && TELEGRAM_ADMIN_ID;
        $debug_message = "* unauthorized access attempt.*\n\n- Your User ID: `{$user_id}`\n- Expected Admin ID is " . ($admin_id_defined ? "set." : "*not set*.");
        send_telegram_message($chat_id, $debug_message);
    }
    exit; // Stop execution for non-admins
}


// Updated keyboard with 'Find User' and 'System Status' buttons
$keyboard = [
    'keyboard' => [
        [['text' => '🔑 授权新邮箱'], ['text' => '🗑 撤销授权']],
        [['text' => '👥 列出用户'], ['text' => '📋 列出授权列表']],
        [['text' => '🔍 查找用户'], ['text' => '📊 系统状态']]
    ],
    'resize_keyboard' => true
];

if (strpos($text, '/add_email') === 0 || strpos($text, '授权新邮箱') !== false) {
    $conn = get_db_or_exit($chat_id, true);
    $email = parse_email_from_command($text);
    if (!$email) {
        send_telegram_message($chat_id, "❌ *格式无效*。\n请使用: `/add_email user@example.com`");
    } else {
        $stmt = $conn->prepare("INSERT INTO allowed_emails (email) VALUES (?);");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) send_telegram_message($chat_id, "✅ *成功!* `{$email}` 现在可以注册了。");
        else send_telegram_message($chat_id, "⚠️ 邮箱 `{$email}` 已在授权列表中。");
        $stmt->close();
    }
    $conn->close();
} elseif ($text === '/list_users' || $text === '👥 列出用户') {
    $conn = get_db_or_exit($chat_id, true);
    $result = $conn->query("SELECT email, created_at FROM users ORDER BY created_at DESC");
    $response = "👥 *已注册的用户:*\n\n";
    if ($result->num_rows > 0) while($row = $result->fetch_assoc()) $response .= "- `{$row['email']}` (注册于 {$row['created_at']})\n";
    else $response = "ℹ️ 暂无已注册的用户。";
    send_telegram_message($chat_id, $response);
    $conn->close();
} elseif ($text === '/list_allowed' || $text === '📋 列出授权列表') {
    $conn = get_db_or_exit($chat_id, true);
    $result = $conn->query("SELECT email, created_at FROM allowed_emails ORDER BY created_at DESC");
    $response = "🔑 *可用于注册的邮箱:*\n\n";
    if ($result->num_rows > 0) while($row = $result->fetch_assoc()) $response .= "- `{$row['email']}` (添加于 {$row['created_at']})\n";
    else $response = "ℹ️ 授权列表为空。";
    send_telegram_message($chat_id, $response);
    $conn->close();
} elseif (strpos($text, '/delete_user') === 0) {
    $conn = get_db_or_exit($chat_id, true);
    $email = parse_email_from_command($text);
    if (!$email) send_telegram_message($chat_id, "❌ *格式无效*。\n请使用: `/delete_user user@example.com`");
    else {
        $stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute() && $stmt->affected_rows > 0) send_telegram_message($chat_id, "✅ *成功!* 用户 `{$email}` 已被删除。");
        else send_telegram_message($chat_id, "⚠️ 未找到用户 `{$email}`。");
        $stmt->close();
    }
    $conn->close();
} elseif (strpos($text, '/revoke_email') === 0 || strpos($text, '撤销授权') !== false) {
    $conn = get_db_or_exit($chat_id, true);
    $email = parse_email_from_command($text);
    if (!$email) send_telegram_message($chat_id, "❌ *格式无效*。\n请使用: `/revoke_email user@example.com`");
    else {
        $stmt = $conn->prepare("DELETE FROM allowed_emails WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute() && $stmt->affected_rows > 0) send_telegram_message($chat_id, "✅ *成功!* `{$email}` 的注册权限已被撤销。");
        else send_telegram_message($chat_id, "⚠️ 在授权列表中未找到 `{$email}`。");
        $stmt->close();
    }
    $conn->close();
} elseif (strpos($text, '/find') === 0 || strpos($text, '🔍 查找用户') !== false) {
    $email = parse_email_from_command($text);
    if (!$email) {
        send_telegram_message($chat_id, "🤔 *请提供要查找的邮箱*。\n请使用: `/find user@example.com`");
    } else {
        $conn = get_db_or_exit($chat_id, true);
        $stmt = $conn->prepare("SELECT email, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response = "🔍 *查找到用户:*\n\n- `{$row['email']}`\n- *注册时间:* {$row['created_at']}";
        } else {
            $response = "⚠️ 未找到用户 `{$email}`。";
        }
        send_telegram_message($chat_id, $response);
        $stmt->close();
        $conn->close();
    }
} elseif ($text === '/status' || $text === '📊 系统状态') {
    $report = "📊 *系统状态报告*\n\n";
    $report .= "- *API 服务 (Webhook)*: 🟢 在线\n";
    $db_conn_status = get_db_for_status();
    if ($db_conn_status) {
        $report .= "- *数据库连接*: 🟢 正常\n";
        $db_conn_status->close();
    } else {
        $report .= "- *数据库连接*: 🔴 失败\n";
    }
    send_telegram_message($chat_id, $report, $keyboard);
} else {
    $help_text = "🤖 *管理员机器人控制台*\n\n您好！请使用下方的键盘或直接发送命令来管理您的应用。";
    send_telegram_message($chat_id, $help_text, $keyboard);
}

?>