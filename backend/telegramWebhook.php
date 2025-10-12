<?php

// --- Telegram Webhook Endpoint ---

// Bootstrap the application
require_once __DIR__ . '/config.php';
// Include helpers specific to the bot's functionality
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/ai_helpers.php';
require_once __DIR__ . '/env_manager.php';


// --- Security Validation ---
$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

// We must have a secret token configured, and it must match what Telegram sends.
if (empty($secretToken) || !hash_equals($secretToken, $receivedToken)) {
    http_response_code(403);
    error_log("Forbidden: Secret token mismatch or not configured.");
    exit('Forbidden');
}

// --- Main Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message'])) {
    // If it's not a message, we don't process it.
    // This could be a channel post, a callback query we don't handle yet, etc.
    exit();
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$userId = $message['from']['id'] ?? $chatId; // Use user ID for state tracking
$text = trim($message['text'] ?? '');

// --- Admin-Only Access Control ---
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
if (empty($adminChatId) || (string)$chatId !== (string)$adminChatId) {
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

// --- State-Driven Conversation Logic ---
$userState = getUserState($userId);

if ($userState) {
    // --- State: Awaiting New API Key ---
    if (strpos($userState, 'awaiting_api_key_') === 0) {
        $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
        // The `update_env_file` function is now in `env_manager.php`
        if (update_env_file($keyToUpdate, $text)) {
            // We don't need to reload env manually anymore, but a confirmation is good.
            sendTelegramMessage($chatId, "✅ API 密钥 `{$keyToUpdate}` 已成功更新！", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！请检查 `.env` 文件的权限。", getAdminKeyboard());
        }
        setUserState($userId, null); // Clear state after action

    // --- State: Awaiting Gemini Prompt ---
    } elseif ($userState === 'awaiting_gemini_prompt') {
        sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
        // `call_gemini_api` is now in `ai_helpers.php`
        $response = call_gemini_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);

    // --- State: Awaiting Email Authorization ---
    } elseif ($userState === 'awaiting_email_authorization') {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            // `authorizeEmail` is in `db_operations.php`, included by config.php
            if (authorizeEmail($text)) {
                sendTelegramMessage($chatId, "✅ 邮箱 `{$text}` 已成功授权。", getAdminKeyboard());
            } else {
                sendTelegramMessage($chatId, "⚠️ 邮箱 `{$text}` 已存在或数据库出错。", getAdminKeyboard());
            }
        } else {
            sendTelegramMessage($chatId, "❌ 无效的邮箱地址，请重新输入。", getAdminKeyboard());
        }
        setUserState($userId, null); // Reset state

    } else {
        // Clear any unknown or residual state.
        setUserState($userId, null);
        sendTelegramMessage($chatId, "系统状态异常，已重置。", getAdminKeyboard());
    }

} else {
    // --- Command Handling (No active state) ---
    switch ($text) {
        case '/start':
        case '返回主菜单':
            sendTelegramMessage($chatId, "欢迎回来，管理员！请选择操作。", getAdminKeyboard());
            break;
        case '授权新邮箱':
            setUserState($userId, 'awaiting_email_authorization');
            sendTelegramMessage($chatId, "请输入您想授权的电子邮件地址。");
            break;
        case '请求 Gemini':
            setUserState($userId, 'awaiting_gemini_prompt');
            sendTelegramMessage($chatId, "请输入您想对 Gemini 说的话。");
            break;
        case '更换 API 密钥':
            sendTelegramMessage($chatId, "请选择要更新的 API 密钥：", getApiKeySelectionKeyboard());
            break;
        case 'Gemini API Key':
            setUserState($userId, 'awaiting_api_key_GEMINI_API_KEY');
            sendTelegramMessage($chatId, "请输入新的 Gemini API 密钥。");
            break;
        case 'DeepSeek API Key':
            setUserState($userId, 'awaiting_api_key_DEEPSEEK_API_KEY');
            sendTelegramMessage($chatId, "请输入新的 DeepSeek API 密钥。");
            break;
        default:
            sendTelegramMessage($chatId, "无法识别的指令，请使用键盘操作。", getAdminKeyboard());
            break;
    }
}

// Finally, acknowledge receipt to Telegram to prevent re-delivery.
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>