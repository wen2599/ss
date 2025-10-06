<?php
// backend/endpoints/tg_webhook.php

// Corrected path to work from the public_html/endpoints directory
require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../../backend/config.php';

// --- Enhanced Debug Logging ---
function log_message($message) {
    // Note: This will attempt to write to public_html/endpoints/debug.log after deployment
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($log_file, $timestamp . " " . $message . "\n", FILE_APPEND);
}

log_message("--- Webhook triggered ---");

// Log raw input
$raw_input = file_get_contents('php://input');
log_message("Raw Input: " . $raw_input);

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
        log_message("DB connection failed.");
        if ($is_admin_command) {
            log_message("Attempting to send DB error message.");
            send_telegram_message($chat_id, "🚨 *数据库错误:* 连接失败。");
        }
        exit;
    }
    log_message("DB connection successful.");
    return $conn;
}

function parse_email_from_command($command_text) {
    $parts = explode(' ', $command_text, 2);
    return filter_var(trim($parts[1] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
}

// --- Main Webhook Logic ---
$update = json_decode($raw_input, true);
if (!$update) {
    log_message("Exit: Failed to decode JSON.");
    exit;
}

log_message("Decoded Update: " . json_encode($update, JSON_UNESCAPED_UNICODE));

$message = $update['message'] ?? $update['channel_post'] ?? null;
if (!$message) {
    log_message("Exit: No message or channel_post found.");
    exit;
}

log_message("Message object found.");

$chat_id = $message['chat']['id'] ?? null;
$text = trim($message['text'] ?? '');
$user_id = $message['from']['id'] ?? null;

log_message("Chat ID: {$chat_id}, User ID: {$user_id}, Text: {$text}");

// --- Branch 1: Process Lottery Results from Channel ---
if (isset($update['channel_post'])) {
    log_message("Entering Branch 1: Channel Post.");
    if ((string)$chat_id === (string)TELEGRAM_CHANNEL_ID) {
        log_message("Channel ID matches. Processing...");
        $lottery_data = parse_lottery_message($text);
        if ($lottery_data) {
            log_message("Lottery data parsed.");
            $conn = get_db_or_exit($chat_id, false);
            $stmt = $conn->prepare("INSERT INTO lottery_results (lottery_type, issue, numbers, zodiacs, colors) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE numbers=VALUES(numbers), zodiacs=VALUES(zodiacs), colors=VALUES(colors)");
            $stmt->bind_param("sssss", $lottery_data['lottery_type'], $lottery_data['issue'], $lottery_data['numbers'], $lottery_data['zodiacs'], $lottery_data['colors']);
            if ($stmt->execute()) log_message("Success: Saved lottery result.");
            else log_message("DB Error: Failed to save lottery result: " . $stmt->error);
            $stmt->close();
            $conn->close();
        } else {
            log_message("No lottery data parsed from text.");
        }
    } else {
        log_message("Channel ID mismatch. Received: {$chat_id}, Expected: " . TELEGRAM_CHANNEL_ID);
    }
    log_message("Exit: End of Channel Post branch.");
    exit;
}

// --- Branch 2: Handle Admin Commands ---
log_message("Entering Branch 2: Admin Commands.");

$admin_id_from_env = defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : '[NOT SET]';
log_message("Admin Check: Received User ID '{$user_id}' (type: " . gettype($user_id) . ") vs. .env Admin ID '{$admin_id_from_env}' (type: " . gettype($admin_id_from_env) . ")");

if ((string)$user_id !== (string)$admin_id_from_env) {
    log_message("Admin check FAILED. User is not admin.");
    if ($chat_id) {
        $debug_message = "* unauthorized access attempt.*\n\n- Your User ID: `{$user_id}`\n- Expected Admin ID is " . ($admin_id_from_env !== '[NOT SET]' ? "set." : "*not set*.");
        log_message("Attempting to send 'unauthorized' message.");
        send_telegram_message($chat_id, $debug_message);
    }
    log_message("Exit: Non-admin user.");
    exit; // Stop execution for non-admins
}

log_message("Admin check PASSED.");

$keyboard = ['keyboard' => [[['text' => '🔑 授权新邮箱'], ['text' => '🗑 撤销授权']], [['text' => '👥 列出用户'], ['text' => '📋 列出授权列表']], [['text' => '🔍 查找用户'], ['text' => '📊 系统状态']]], 'resize_keyboard' => true];

if (strpos($text, '/add_email') === 0 || strpos($text, '授权新邮箱') !== false) {
    log_message("Entering command: add_email");
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
    log_message("Entering command: list_users");
    // ... (rest of the logic) ...
} else {
    log_message("No specific command matched. Sending help text.");
    $help_text = "🤖 *管理员机器人控制台*\n\n您好！请使用下方的键盘或直接发送命令来管理您的应用。";
    send_telegram_message($chat_id, $help_text, $keyboard);
}

log_message("--- Webhook finished ---");

?>