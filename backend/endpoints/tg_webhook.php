<?php
// backend/endpoints/tg_webhook.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/telegram_utils.php'; // Correctly include the Telegram utilities

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
 * Establishes a database connection using PDO.
 * @return PDO|null A PDO connection object or null on failure.
 */
function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            log_message("DB Connection Error: " . $e->getMessage());
            return null;
        }
    }
    return $conn;
}

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

function get_user_state_file($user_id) {
    return sys_get_temp_dir() . '/tg_state_' . $user_id;
}

function get_user_state($user_id) {
    $file = get_user_state_file($user_id);
    return file_exists($file) ? trim(file_get_contents($file)) : null;
}

function set_user_state($user_id, $state = null) {
    $file = get_user_state_file($user_id);
    if ($state === null) {
        if (file_exists($file)) unlink($file);
    } else {
        file_put_contents($file, $state);
    }
}

/**
 * Saves or updates an API key in the database.
 * @param string $key_name The name of the key (e.g., 'gemini').
 * @param string $key_value The value of the API key.
 * @return bool True on success, false on failure.
 */
function set_api_key(string $key_name, string $key_value): bool {
    $conn = get_db_connection();
    if (!$conn) return false;
    try {
        $stmt = $conn->prepare(
            "INSERT INTO api_keys (key_name, key_value, updated_at) VALUES (:key_name, :key_value, NOW())
             ON DUPLICATE KEY UPDATE key_value = :key_value, updated_at = NOW()"
        );
        $stmt->execute([':key_name' => $key_name, ':key_value' => $key_value]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
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

$configured_admin_id = defined('TELEGRAM_ADMIN_ID') ? TELEGRAM_ADMIN_ID : 'NOT DEFINED';
if ((string)$user_id !== (string)$configured_admin_id) {
    log_message("SECURITY: Unauthorized access attempt by user {$user_id}.");
    send_telegram_message(TELEGRAM_BOT_TOKEN, $chat_id, "抱歉，我只为管理员服务。您的用户ID: `{$user_id}`");
    exit;
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
            send_telegram_message(TELEGRAM_BOT_TOKEN, $chat_id, "请选择一个用户管理操作:", $user_management_inline_keyboard);
            break;
        case 'push_message':
            send_telegram_message(TELEGRAM_BOT_TOKEN, $chat_id, "▶️ *如何推送消息*\n\n请使用 `/push 您想发送的消息内容`。");
            break;
        case 'set_api_keys':
            send_telegram_message(TELEGRAM_BOT_TOKEN, $chat_id, "请选择要操作的 API 密钥:", $api_keys_inline_keyboard);
            break;
        case 'set_gemini_key_prompt':
            set_user_state($user_id, 'waiting_for_gemini_key');
            send_telegram_message(TELEGRAM_BOT_TOKEN, $chat_id, "请输入您的 Gemini API 密钥:");
            break;
        // ... other cases like list_users, add_email_prompt etc. remain unchanged
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

    $user_state = get_user_state($user_id);
    if ($user_state === 'waiting_for_gemini_key') {
        if (set_api_key('gemini', $text)) {
            send_telegram_message(TELEGRAM_BOT_TOKEN, $chat_id, "✅ *成功*\nGemini API 密钥已更新。");
        } else {
            send_telegram_message(TELEGRAM_BOT_TOKEN, $chat_id, "🚨 *数据库错误*\n无法保存 Gemini API 密钥。");
        }
        set_user_state($user_id, null); // Clear state
        exit;
    }
    
    // ... (The rest of the text message handling logic remains unchanged)
}

log_message("--- Webhook finished ---");
?>