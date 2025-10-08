<?php
// backend/endpoints/telegram_webhook.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/telegram_utils.php'; // Correctly include the Telegram utilities

use Illuminate\Database\Capsule\Manager as Capsule;

// --- Helper Functions ---
function log_message($message) {
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('[Y-m-d H:i:s]');
    $log_entry = $timestamp . " " . $message . "\n";
    if (file_put_contents($log_file, $log_entry, FILE_APPEND) === false) {
        error_log("CRITICAL: Failed to write to log file: " . $log_file);
    }
}

// --- Re-implemented Helper Functions ---

/**
 * Acknowledges a Telegram callback query to stop the loading icon.
 * @param string $callback_query_id The ID of the callback query.
 */
function answer_callback_query(string $callback_query_id): void {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/answerCallbackQuery";
    $post_fields = ['callback_query_id' => $callback_query_id];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        log_message("cURL error answering callback query: " . curl_error($ch));
    }
    curl_close($ch);
}

/**
 * Sets the conversational state for a user in the database.
 *
 * @param int $user_id The Telegram user ID.
 * @param string|null $state The state to set, or null to clear the state.
 * @param array|null $state_data Optional data to store along with the state.
 */
function set_user_state(int $user_id, ?string $state, ?array $state_data = null): void {
    if ($state === null) {
        Capsule::table('user_states')->where('user_id', $user_id)->delete();
    } else {
        Capsule::table('user_states')->updateOrInsert(
            ['user_id' => $user_id],
            [
                'state' => $state,
                'state_data' => $state_data ? json_encode($state_data) : null,
                'updated_at' => new \DateTime() // Keep the timestamp fresh
            ]
        );
    }
}

/**
 * Gets the conversational state for a user from the database.
 *
 * @param int $user_id The Telegram user ID.
 * @return object|null The state record (e.g., with 'state' and 'state_data' properties) or null if no state is set.
 */
function get_user_state(int $user_id): ?object {
    $state_record = Capsule::table('user_states')->where('user_id', $user_id)->first();
    if ($state_record && !is_null($state_record->state_data)) {
        // Decode the JSON data before returning
        $state_record->state_data = json_decode($state_record->state_data, true);
    }
    return $state_record;
}

/**
 * Retrieves an API key from the database.
 *
 * @param string $key_name The name of the key to retrieve.
 * @return string|null The key value or null if not found.
 */
function get_api_key(string $key_name): ?string {
    $key = Capsule::table('api_keys')->where('key_name', $key_name)->first();
    return $key ? $key->key_value : null;
}

/**
 * Saves or updates an API key in the database.
 *
 * @param string $key_name The name of the key (e.g., 'gemini').
 * @param string $key_value The value of the API key.
 * @return bool True on success, false on failure.
 */
function set_api_key(string $key_name, string $key_value): bool {
    try {
        Capsule::table('api_keys')->updateOrInsert(
            ['key_name' => $key_name],
            ['key_value' => $key_value, 'updated_at' => new \DateTime()]
        );
        return true;
    } catch (\Exception $e) {
        log_message("Failed to set API key '{$key_name}': " . $e->getMessage());
        return false;
    }
}


// --- Main Entry Point ---
log_message("--- Webhook triggered ---");
$raw_input = file_get_contents('php://input');
log_message("Raw Input: " . $raw_input);
$update = json_decode($raw_input, true);

if (!$update) {
    log_message("Exit: Failed to decode JSON.");
    exit;
}

// --- BRANCH 1: Process Channel Posts (Publicly Accessible Logic) ---
// This branch has its own security (checking channel_id) and runs before the admin-only gate.
if (isset($update['channel_post'])) {
    log_message("--- CHANNEL POST RECEIVED ---");
    $channel_id = $update['channel_post']['chat']['id'] ?? null;
    $post_text = $update['channel_post']['text'] ?? '';

    $configured_channel_id = defined('TELEGRAM_CHANNEL_ID') ? TELEGRAM_CHANNEL_ID : null;
    if (!$configured_channel_id || (string)$channel_id !== (string)$configured_channel_id) {
        log_message("SECURITY: Ignoring post from unauthorized channel {$channel_id}.");
        exit;
    }
    
    // ... (The rest of the channel post processing logic remains unchanged)
    log_message("--- Channel Post processing finished ---");
    exit;
}

// --- ABSOLUTE SECURITY GATE: All further actions must be from the Admin ---
$user_id = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
$chat_id = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;

if (!$user_id || !$chat_id) {
    log_message("Exit: Could not determine user/chat ID for non-channel-post update.");
    exit;
}

$configured_admin_id = defined('TELEGRAM_ADMIN_ID') ? trim(TELEGRAM_ADMIN_ID) : null;

if (!$configured_admin_id || (string)$user_id !== $configured_admin_id) {
    $error_message = " unauthorized access by user `{$user_id}`.";

    if (!$configured_admin_id) {
        // This case is critical: the .env variable is missing entirely.
        log_message("SECURITY: TELEGRAM_ADMIN_ID is not configured." . $error_message);
        // We might not have a token to send a message, but we try.
        send_telegram_message($chat_id, "🚨 **致命错误** 🚨\n机器人未配置管理员ID，无法运行。请联系开发者。");
    } else {
        // This case is a standard permissions issue.
        log_message("SECURITY: Denied" . $error_message . " Expected admin ID: {$configured_admin_id}.");
        send_telegram_message(
            $chat_id,
            "🔐 **访问被拒绝** 🔐\n\n抱歉，我只为授权管理员服务。\n\n您的用户 ID 是:\n`{$user_id}`\n\n请将此 ID 提供给机器人管理员进行配置。"
        );
    }
    exit; // Stop execution for unauthorized users.
}

// If we reach here, the user is the authenticated admin.
log_message("Admin check PASSED for user {$user_id}. Proceeding with admin-only logic.");

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

// --- BRANCH 2: Handle Callbacks from Admin ---
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_data = $callback_query['data'];
    answer_callback_query($callback_query['id']);
    log_message("Entering Admin Branch 2: Callback Query. Data: {$callback_data}");

    switch ($callback_data) {
        case 'user_management':
            send_telegram_message($chat_id, "请选择一个用户管理操作:", $user_management_inline_keyboard);
            break;
        case 'push_message':
            send_telegram_message($chat_id, "▶️ *如何推送消息*\n\n请使用 `/push 您想发送的消息内容`。");
            break;
        case 'add_email_prompt':
            set_user_state($user_id, 'waiting_for_email');
            send_telegram_message($chat_id, "请输入您想要授权的邮箱地址:");
            break;
        case 'list_users':
            $users = Capsule::table('users')->get();
            $message = "👤 *注册用户列表*\n\n";
            if ($users->isEmpty()) {
                $message .= "没有已注册的用户。";
            } else {
                foreach ($users as $user) {
                    $message .= "- `{$user->email}` (ID: {$user->id})\n";
                }
            }
            send_telegram_message($chat_id, $message);
            break;
        case 'list_allowed':
            $emails = Capsule::table('allowed_emails')->get();
            $message = "📋 *授权邮箱列表*\n\n";
            if ($emails->isEmpty()) {
                $message .= "没有已授权的邮箱。";
            } else {
                foreach ($emails as $email) {
                    $message .= "- `{$email->email}`\n";
                }
            }
            send_telegram_message($chat_id, $message);
            break;
        case 'set_api_keys':
            send_telegram_message($chat_id, "请选择要操作的 API 密钥:", $api_keys_inline_keyboard);
            break;
        case 'set_gemini_key_prompt':
            set_user_state($user_id, 'waiting_for_gemini_key');
            send_telegram_message($chat_id, "请输入您的 Gemini API 密钥:");
            break;
        default:
            log_message("Unhandled admin callback: {$callback_data}");
            break;
    }
    exit;
}

// --- BRANCH 3: Handle Text Messages from Admin ---
if (isset($update['message'])) {
    $text = trim($update['message']['text'] ?? '');
    log_message("Entering Admin Branch 3: Text Message. Text: {$text}");

    $state_record = get_user_state($user_id);
    if ($state_record && $state_record->state === 'waiting_for_email') {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            try {
                Capsule::table('allowed_emails')->insert(['email' => $text]);
                send_telegram_message($chat_id, "✅ *成功*\n邮箱 `{$text}` 已被授权。");
            } catch (\Exception $e) {
                // Handle potential duplicate entry
                send_telegram_message($chat_id, "⚠️ *提醒*\n邮箱 `{$text}` 已存在，无需重复添加。");
            }
        } else {
            send_telegram_message($chat_id, "🚨 *错误*\n您输入的 `{$text}` 不是一个有效的邮箱地址，请重新输入。");
        }
        set_user_state($user_id, null); // Clear state
        exit;
    } elseif ($state_record && $state_record->state === 'waiting_for_gemini_key') {
        if (set_api_key('gemini', $text)) {
            send_telegram_message($chat_id, "✅ *成功*\nGemini API 密钥已更新。");
        } else {
            send_telegram_message($chat_id, "🚨 *数据库错误*\n无法保存 Gemini API 密钥。");
        }
        set_user_state($user_id, null); // Clear state
        exit;
    }
    
    // ... (The rest of the text message handling logic remains unchanged)
}

log_message("--- Webhook finished ---");
?>