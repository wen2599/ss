
<?php
// backend/endpoints/tg_webhook.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

// --- Enhanced Debug Logging ---
function log_message($message) {
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('[Y-m-d H:i:s]');
    $log_entry = $timestamp . " " . $message . "\n";
    if (file_put_contents($log_file, $log_entry, FILE_APPEND) === false) {
        error_log("CRITICAL: Failed to write to log file at: " . $log_file . ". Check permissions.");
        exit; // Stop execution if logging fails
    }
}

// --- Main entry point ---
log_message("--- Webhook triggered ---");
$raw_input = file_get_contents('php://input');
log_message("Raw Input: " . $raw_input);
$update = json_decode($raw_input, true);

if (!$update) {
    log_message("Exit: Failed to decode JSON.");
    exit;
}

// --- Helper Functions ---
function get_db_or_exit($chat_id) {
    $conn = get_db_connection();
    if (!$conn) {
        log_message("DB connection failed.");
        send_telegram_message($chat_id, "🚨 *数据库错误:* 连接失败。");
        exit;
    }
    log_message("DB connection successful.");
    return $conn;
}

function parse_email_from_command($command_text) {
    $parts = explode(' ', $command_text, 2);
    return filter_var(trim($parts[1] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
}

// --- BRANCH 1: Process Channel Posts for Lottery Results ---
// ... (lottery logic remains the same)

// --- Security Gate: Check for Admin ID ---
$user_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
$chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;

if (!$user_id || !$chat_id) {
    log_message("Exit: Could not determine user or chat ID.");
    exit;
}

if ((string)$user_id !== (string)TELEGRAM_ADMIN_ID) {
    log_message("SECURITY: Unauthorized access by user {$user_id}.");
    send_telegram_message($chat_id, "抱歉，我只为管理员服务。您的用户ID: `{$user_id}`");
    exit;
}
log_message("Admin check PASSED for user {$user_id}.");

// --- BRANCH 2: Handle Callbacks from Inline Keyboards (Post Buttons) ---
// ... (callback logic remains the same)

// --- BRANCH 3: Handle Regular Text Messages from Admin ---
if (isset($update['message'])) {
    $text = trim($update['message']['text'] ?? '');
    log_message("Entering Branch 3: Text Message. Text: {$text}");

    // --- Define Keyboards ---
    $main_reply_keyboard = ['keyboard' => [[['text' => '📣 推送消息'], ['text' => '👤 用户与授权']], [['text' => '📊 系统状态'], ['text' => '❓ 帮助']]], 'resize_keyboard' => true, 'one_time_keyboard' => false];
    $user_management_inline_keyboard = ['inline_keyboard' => [
        [['text' => '👥 列出注册用户', 'callback_data' => 'list_users'], ['text' => '📋 列出授权邮箱', 'callback_data' => 'list_allowed']],
        [['text' => 'ℹ️ 操作方法', 'callback_data' => 'auth_help']]
    ]];

    // --- Command Routing ---
    if (strpos($text, '/push') === 0) {
        $parts = explode(' ', $text, 2);
        $broadcast_message = $parts[1] ?? '';

        if (empty($broadcast_message)) {
            send_telegram_message($chat_id, "❌ *格式无效*。\n请使用: `/push 您想发送的消息`");
        } else {
            send_telegram_message($chat_id, "⏳ 正在准备推送，请稍候...");
            $conn = get_db_or_exit($chat_id);
            $result = $conn->query("SELECT tg_user_id FROM users WHERE tg_user_id IS NOT NULL;");
            
            $user_ids = [];
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $user_ids[] = $row['tg_user_id'];
                }
            } 
            $conn->close();

            if (empty($user_ids)) {
                send_telegram_message($chat_id, "🤷‍♀️ 找不到任何已注册的用户来进行推送。");
            } else {
                $success_count = 0;
                $fail_count = 0;
                foreach ($user_ids as $target_user_id) {
                    try {
                        send_telegram_message($target_user_id, $broadcast_message);
                        $success_count++;
                    } catch (Exception $e) {
                        log_message("Broadcast failed for user {$target_user_id}: " . $e->getMessage());
                        $fail_count++;
                    }
                    usleep(500000); // 0.5秒延迟，防止触发速率限制
                }
                $summary_message = "✅ *推送完成*\n\n";
                $summary_message .= "▫️ 成功发送: *{$success_count}* 位用户\n";
                $summary_message .= "▫️ 发送失败: *{$fail_count}* 位用户";
                send_telegram_message($chat_id, $summary_message);
            }
        }

    } else if (strpos($text, '/add_email') === 0) {
        // ... (add_email logic remains the same)
    } else if (strpos($text, '/remove_email') === 0) {
        // ... (remove_email logic remains the same)
    } else {
        switch ($text) {
            case '📣 推送消息':
                $push_help = "▶️ *如何推送消息*\n\n";
                $push_help .= "请使用以下命令格式向所有已注册用户发送广播:\n\n";
                $push_help .= "`/push 您想发送的消息内容`\n\n";
                $push_help .= "例如: `/push 大家好，今晚系统将进行维护。`";
                send_telegram_message($chat_id, $push_help);
                break;
            // ... (other cases like /start, user management, status remain the same)
            case '/start':
            case '❓ 帮助':
                $help_text = "🤖 *管理员机器人控制台*\n\n您好！请使用下方的键盘导航。";
                send_telegram_message($chat_id, $help_text, $main_reply_keyboard);
                break;
                
            case '👤 用户与授权':
                send_telegram_message($chat_id, "请选择一个用户管理操作:", $user_management_inline_keyboard);
                break;
                
            case '📊 系统状态':
                $db_status = (get_db_connection()) ? "✅ 连接正常" : "❌ 连接失败";
                $admin_id = defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : "未设置";
                $channel_id = defined('TELEGRAM_CHANNEL_ID') ? TELEGRAM_CHANNEL_ID : "未设置";
                $status_message = "*系统状态*\n\n";
                $status_message .= "*数据库:* {$db_status}\n";
                $status_message .= "*管理员ID:* `{$admin_id}`\n";
                $status_message .= "*频道ID:* `{$channel_id}`";
                send_telegram_message($chat_id, $status_message);
                break;

            default:
                $help_text = "我不明白您的意思。请使用下方的键盘或发送 `/start` 来显示主菜单。";
                send_telegram_message($chat_id, $help_text, $main_reply_keyboard);
                break;
        }
    }
}

log_message("--- Webhook finished ---");
?>
