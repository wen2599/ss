<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';

// --- Security Validation ---
$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!empty($secretToken) && $receivedToken !== $secretToken) {
    http_response_code(403);
    error_log("Forbidden: Secret token mismatch.");
    exit('Forbidden: Secret token mismatch.');
}

// --- Main Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    exit();
}

// --- Determine Update Type and Extract Data ---
$chatId = null;
$userId = null;
$text = null;
$callbackQueryId = null;
$isCallback = false;

if (isset($update['callback_query'])) {
    $isCallback = true;
    $callbackQuery = $update['callback_query'];
    $callbackQueryId = $callbackQuery['id'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $text = $callbackQuery['data']; // Use callback_data as the command
    answerCallbackQuery($callbackQueryId); // Acknowledge the button press immediately
} elseif (isset($update['message']) || isset($update['channel_post'])) {
    $message = $update['message'] ?? $update['channel_post'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'] ?? $chatId; // Fallback to chatId if 'from' is not set (e.g., in channels)
    $text = trim($message['text'] ?? '');
} else {
    exit(); // Exit if it's an unsupported update type
}


// --- Admin Verification ---
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
if (empty($adminChatId) || (string)$chatId !== (string)$adminChatId) {
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

// --- Centralized Handler Function ---
handleRequest($userId, $chatId, $text, $isCallback);

http_response_code(200);
echo json_encode(['status' => 'ok']);


/**
 * Handles all incoming requests, both text messages and callback queries.
 *
 * @param int    $userId     The user's Telegram ID.
 * @param int    $chatId     The chat's Telegram ID.
 * @param string $text       The command or message text.
 * @param bool   $isCallback True if the request is from a callback query.
 */
function handleRequest($userId, $chatId, $text, $isCallback) {
    $userState = getUserState($userId);

    // --- State-Driven Conversation Logic ---
    if ($userState && !$isCallback) { // Callbacks should not be affected by text input states
        if (strpos($userState, 'awaiting_api_key_') === 0) {
            $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
            if (update_env_file($keyToUpdate, $text)) {
                sendTelegramMessage($chatId, "✅ API 密钥 `{$keyToUpdate}` 已成功更新！新配置已生效。", getAdminKeyboard());
            } else {
                sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！", getAdminKeyboard());
            }
            setUserState($userId, null);
        } elseif ($userState === 'awaiting_gemini_prompt') {
            sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
            $response = call_gemini_api($text);
            sendTelegramMessage($chatId, $response, getAdminKeyboard());
            setUserState($userId, null);
        } elseif ($userState === 'awaiting_cloudflare_prompt') {
            sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
            $response = call_cloudflare_ai_api($text);
            sendTelegramMessage($chatId, $response, getAdminKeyboard());
            setUserState($userId, null);
        } elseif ($userState === 'awaiting_user_deletion') {
            if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                if (deleteUserByEmail($text)) {
                    sendTelegramMessage($chatId, "✅ 用户 `{$text}` 已成功删除。", getUserManagementKeyboard());
                } else {
                    sendTelegramMessage($chatId, "⚠️ 删除失败。用户 `{$text}` 不存在或数据库出错。", getUserManagementKeyboard());
                }
            } else {
                sendTelegramMessage($chatId, "❌ 无效的邮箱地址。请重新输入或返回。", getUserManagementKeyboard());
            }
            setUserState($userId, null);
        } else {
            setUserState($userId, null); // Clear invalid state
            sendTelegramMessage($chatId, "系统状态异常，已重置。", getAdminKeyboard());
        }
        return; // Stop further processing
    }

    // --- Command/Callback Logic ---
    $messageToSend = null;
    $keyboard = null;

    switch ($text) {
        case '/start':
        case 'main_menu':
            $messageToSend = "欢迎回来，管理员！请选择一个操作。";
            $keyboard = getAdminKeyboard();
            break;

        // --- User Management ---
        case 'user_management':
            $messageToSend = "请选择一个用户管理操作:";
            $keyboard = getUserManagementKeyboard();
            break;
        case 'list_users':
            $users = getAllUsers();
            if (empty($users)) {
                $messageToSend = "数据库中没有找到任何用户。";
            } else {
                $messageToSend = "注册用户列表:\n\n";
                foreach ($users as $user) {
                    $messageToSend .= "📧 **邮箱:** `{$user['email']}`\n";
                    $messageToSend .= "📅 **注册于:** {$user['created_at']}\n\n";
                }
            }
            $keyboard = getUserManagementKeyboard();
            break;
        case 'delete_user':
            setUserState($userId, 'awaiting_user_deletion');
            $messageToSend = "好的，请发送您想要删除的用户的电子邮件地址。";
            break;

        // --- AI & API Management ---
        case 'ask_gemini':
            setUserState($userId, 'awaiting_gemini_prompt');
            $messageToSend = "好的，请直接输入您想对 Gemini 说的话。";
            break;
        case 'ask_cloudflare':
            setUserState($userId, 'awaiting_cloudflare_prompt');
            $messageToSend = "好的，请直接输入您想对 Cloudflare AI 说的话。";
            break;
        case 'change_api_key':
            $messageToSend = "请选择您想要更新的 API 密钥：";
            $keyboard = getApiKeySelectionKeyboard();
            break;
        case (preg_match('/^set_api_key_/', $text) ? $text : !$text):
            $keyToSet = substr($text, strlen('set_api_key_'));
            setUserState($userId, 'awaiting_api_key_' . $keyToSet);
            $messageToSend = "好的，请发送您的新 `{$keyToSet}`。";
            break;

        default:
            if (!$isCallback) { // Only show error for actual messages, not unknown callbacks
                $messageToSend = "无法识别的指令，请使用下方键盘操作。";
                $keyboard = getAdminKeyboard();
            }
            break;
    }

    if ($messageToSend) {
        sendTelegramMessage($chatId, $messageToSend, $keyboard);
    }
}
?>