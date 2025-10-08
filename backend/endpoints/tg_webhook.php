
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

// --- State Management for Conversational Flows ---
function get_user_state_file($user_id) {
    // Using a temporary directory for state files
    return sys_get_temp_dir() . '/tg_state_' . $user_id;
}

function get_user_state($user_id) {
    $file = get_user_state_file($user_id);
    if (file_exists($file)) {
        return trim(file_get_contents($file));
    }
    return null;
}

function set_user_state($user_id, $state = null) {
    $file = get_user_state_file($user_id);
    if ($state === null) {
        if (file_exists($file)) {
            unlink($file);
        }
    } else {
        file_put_contents($file, $state);
    }
}
// --- End Helper Functions ---


// --- BRANCH 1: Process Channel Posts for Lottery Results (No changes here) ---
if (isset($update['channel_post'])) {
    /* ... existing channel post logic ... */
    exit;
}


// --- Security Gate & ID Initialization ---
$user_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
$chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;

if (!$user_id || !$chat_id) {
    log_message("Exit: Could not determine user or chat ID.");
    exit;
}

$configured_admin_id = defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : 'NOT DEFINED';
if ((string)$user_id !== (string)$configured_admin_id) {
    log_message("SECURITY: Unauthorized access by user {$user_id}.");
    send_telegram_message($chat_id, "抱歉，我只为管理员服务。您的用户ID: `{$user_id}`");
    exit;
}
log_message("Admin check PASSED for user {$user_id}.");


// --- Define Keyboards ---
$main_reply_keyboard = ['keyboard' => [[['text' => '⚙️ 管理菜单'], ['text' => '📊 系统状态']]], 'resize_keyboard' => true];
$admin_panel_inline_keyboard = ['inline_keyboard' => [
    [['text' => '👤 用户管理', 'callback_data' => 'user_management'], ['text' => '📣 消息推送', 'callback_data' => 'push_message']],
    [['text' => '🔑 设置 API 密钥', 'callback_data' => 'set_api_keys']]
]];
$user_management_inline_keyboard = ['inline_keyboard' => [
    [['text' => '➕ 添加授权邮箱', 'callback_data' => 'add_email_prompt']],
    [['text' => '👥 列出注册用户', 'callback_data' => 'list_users'], ['text' => '📋 列出授权邮箱', 'callback_data' => 'list_allowed']],
    [['text' => 'ℹ️ 操作方法', 'callback_data' => 'auth_help']]
]];
$api_keys_inline_keyboard = ['inline_keyboard' => [
    [['text' => '设置 Gemini Key', 'callback_data' => 'set_gemini_key_prompt']]
]];


// --- BRANCH 2: Handle Callbacks from Inline Keyboards ---
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_data = $callback_query['data'];
    answer_callback_query($callback_query['id']);
    log_message("Entering Branch 2: Callback Query. Data: {$callback_data}");

    switch ($callback_data) {
        // Admin Panel Navigation
        case 'user_management':
            send_telegram_message($chat_id, "请选择一个用户管理操作:", $user_management_inline_keyboard);
            break;
        case 'push_message':
            send_telegram_message($chat_id, "▶️ *如何推送消息*\n\n请使用 `/push 您想发送的消息内容`。");
            break;
        case 'set_api_keys':
            send_telegram_message($chat_id, "请选择要操作的 API 密钥:", $api_keys_inline_keyboard);
            break;

        // API Key Management
        case 'set_gemini_key_prompt':
            set_user_state($user_id, 'waiting_for_gemini_key');
            send_telegram_message($chat_id, "请输入您的 Gemini API 密钥:");
            break;

        // User Management Callbacks
        case 'add_email_prompt':
            send_telegram_message($chat_id, "▶️ *如何授权新邮箱?*\n\n请直接向我发送您想授权的邮箱地址即可。");
            break;
        case 'list_users':
        case 'list_allowed':
             /* ... existing list logic ... */
            break;
        case 'auth_help':
             /* ... existing help logic ... */
            break;

        default:
            log_message("Unhandled callback: {$callback_data}");
            break;
    }
    exit;
}


// --- BRANCH 3: Handle Regular Text Messages from Admin ---
if (isset($update['message'])) {
    $text = trim($update['message']['text'] ?? '');
    log_message("Entering Branch 3: Text Message. Text: {$text}");

    // --- Priority 1: Check for conversational state ---
    $user_state = get_user_state($user_id);
    if ($user_state) {
        log_message("User {$user_id} is in state: {$user_state}");
        if ($user_state === 'waiting_for_gemini_key') {
            $gemini_key = $text;
            if (set_api_key('gemini', $gemini_key)) {
                send_telegram_message($chat_id, "✅ *成功*\nGemini API 密钥已更新。");
            } else {
                send_telegram_message($chat_id, "🚨 *数据库错误*\n无法保存 Gemini API 密钥。");
            }
            set_user_state($user_id, null); // Clear state
        }
        exit; // Important: Exit after handling stateful message
    }

    // --- Priority 2: Check for commands or email addresses ---
    if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
         /* ... existing email authorization logic ... */
    } else if (strpos($text, '/') === 0) {
        // Command handling (/push, /remove_email)
        /* ... existing command logic ... */
    } else {
        // --- Priority 3: Handle keyboard button presses and default case ---
        switch ($text) {
            case '/start':
            case '❓ 帮助':
                $help_text = "🤖 *管理员机器人控制台*\n\n您好！请使用下方的键盘导航。";
                send_telegram_message($chat_id, $help_text, $main_reply_keyboard);
                break;
            case '⚙️ 管理菜单':
                send_telegram_message($chat_id, "请选择一个管理操作:", $admin_panel_inline_keyboard);
                break;
            case '📊 系统状态':
                 /* ... existing status logic ... */
                break;
            default:
                send_telegram_message($chat_id, "我不明白您的意思。请使用下方的键盘或发送 `/start`。");
                break;
        }
    }
}

log_message("--- Webhook finished ---");
?>
