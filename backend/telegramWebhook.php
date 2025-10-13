<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';

// --- Security Validation ---
$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (empty($secretToken) || $receivedToken !== $secretToken) {
    http_response_code(403);
    exit('Forbidden: Secret token mismatch.');
}

// --- Main Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update || !isset($update['message'])) {
    exit();
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$userId = $message['from']['id'] ?? $chatId;
$text = trim($message['text'] ?? '');

// --- Admin Verification ---
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
if (empty($adminChatId) || (string)$chatId !== (string)$adminChatId) {
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

// --- State-Driven Conversation Logic ---
$userState = getUserState($userId);

// This block handles responses when the user is in a specific conversation state.
if ($userState) {
    // --- State: Awaiting New API Key ---
    if (strpos($userState, 'awaiting_api_key_') === 0) {
        $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
        if (update_env_file($keyToUpdate, $text)) {
            sendTelegramMessage($chatId, "✅ API 密钥 `{$keyToUpdate}` 已成功更新！新配置已生效。", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！请检查 `.env` 文件的权限和路径是否正确。", getAdminKeyboard());
        }
        if (!setUserState($userId, null)) {
            sendTelegramMessage($chatId, "⚠️ 系统警告：无法更新用户状态，请检查服务器文件权限。");
        }

    // --- State: Awaiting Gemini Prompt ---
    } elseif ($userState === 'awaiting_gemini_prompt') {
        sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
        $response = call_gemini_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        if (!setUserState($userId, null)) {
            sendTelegramMessage($chatId, "⚠️ 系统警告：无法更新用户状态，请检查服务器文件权限。");
        }
    
    } elseif ($userState === 'awaiting_cloudflare_prompt') {
        sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
        $response = call_cloudflare_ai_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        if (!setUserState($userId, null)) {
            sendTelegramMessage($chatId, "⚠️ 系统警告：无法更新用户状态，请检查服务器文件权限。");
        }

    // --- State: Awaiting Email Authorization ---
    } elseif ($userState === 'awaiting_email_authorization') {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            if (authorizeEmail($text)) {
                sendTelegramMessage($chatId, "✅ 邮箱 `{$text}` 已成功授权，用户现在可以凭此邮箱注册。", getAdminKeyboard());
            } else {
                sendTelegramMessage($chatId, "⚠️ 邮箱 `{$text}` 已存在或数据库出错，无法重复授权。", getAdminKeyboard());
            }
        } else {
            sendTelegramMessage($chatId, "❌ 您输入的不是一个有效的邮箱地址，请重新输入或点击 '返回主菜单'。", getAdminKeyboard());
        }
        if (!setUserState($userId, null)) { // Reset state after one attempt.
            sendTelegramMessage($chatId, "⚠️ 系统警告：无法更新用户状态，请检查服务器文件权限。");
        }

    } else {
        if (!setUserState($userId, null)) { // Clear invalid state
             sendTelegramMessage($chatId, "⚠️ 系统警告：无法重置用户状态，请检查服务器文件权限。");
        }
        sendTelegramMessage($chatId, "系统状态异常，已重置。请重新选择操作。", getAdminKeyboard());
    }

// This block handles initial commands when the user is not in a specific state.
} else {
    $stateToSet = null;
    $messageToSend = null;
    $keyboard = getAdminKeyboard();

    switch ($text) {
        case '/start':
        case '/':
            $messageToSend = "欢迎回来，管理员！请选择一个操作。";
            break;
        case '授权新邮箱':
            $stateToSet = 'awaiting_email_authorization';
            $messageToSend = "好的，请发送您想要授权注册的电子邮件地址。";
            $keyboard = null; // No keyboard when asking for input
            break;
        case '请求 Gemini':
            $stateToSet = 'awaiting_gemini_prompt';
            $messageToSend = "好的，请直接输入您想对 Gemini 说的话。";
            $keyboard = null; // No keyboard when asking for input
            break;
        case '请求 Cloudflare':
            $stateToSet = 'awaiting_cloudflare_prompt';
            $messageToSend = "好的，请直接输入您想对 Cloudflare AI 说的话。";
            $keyboard = null; // No keyboard when asking for input
            break;
        case '更换 API 密钥':
            $messageToSend = "请选择您想要更新的 API 密钥：";
            $keyboard = getApiKeySelectionKeyboard();
            break;
        case 'Gemini API Key':
            $stateToSet = 'awaiting_api_key_GEMINI_API_KEY';
            $messageToSend = "好的，请发送您的新 Gemini API 密钥。";
            $keyboard = null; // No keyboard when asking for input
            break;
        case '返回主菜单':
            $stateToSet = null;
            $messageToSend = "已返回主菜单。";
            break;
        default:
            $messageToSend = "无法识别的指令，请使用下方键盘操作。";
            break;
    }

    if ($stateToSet !== null || in_array($text, ['/start', '/', '返回主菜单', '更换 API 密钥'])) {
        if (!setUserState($userId, $stateToSet)) {
            sendTelegramMessage($chatId, "⚠️ 系统警告：无法更新用户状态，请检查服务器文件权限。");
        }
    }

    if ($messageToSend) {
        sendTelegramMessage($chatId, $messageToSend, $keyboard);
    }
}

// Acknowledge receipt to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);

?>