<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram_helpers.php';

// This check ensures that the main webhook logic only runs when the script is called directly,
// not when it's included by another script (like our test_logic.php).
if (!defined('IS_LOGIC_TEST')) {
    // --- Security Validation ---
    $secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
    $receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

    if (empty($secretToken) || $receivedToken !== $secretToken) {
        http_response_code(403);
        exit('Forbidden: Secret token mismatch.');
    }

    // --- Main Webhook Logic ---
    $update = json_decode(file_get_contents('php://input'), true);

    // We only process 'message' updates in this simplified model.
    if (!isset($update['message'])) {
        exit();
    }

    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'] ?? $chatId;
    $command = trim($message['text'] ?? '');

    // --- Admin Verification ---
    $adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
    if (empty($adminChatId) || (string)$chatId !== (string)$adminChatId) {
        sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
        exit();
    }

    // --- Process the Command ---
    processCommand($chatId, $userId, $command);
}


/**
 * Processes the user's command.
 *
 * @param int    $chatId  The chat ID to send responses to.
 * @param int    $userId  The user ID for state management.
 * @param string $command The command or text input from the user.
 */
function processCommand($chatId, $userId, $command) {
    $userState = getUserState($userId);

    if ($userState) {
        handleStatefulInteraction($chatId, $userId, $command, $userState);
    } else {
        handleCommand($chatId, $userId, $command);
    }
}

/**
 * Handles interactions when the user is in a specific state (e.g., awaiting input).
 */
function handleStatefulInteraction($chatId, $userId, $text, $userState) {
    $stateCleared = false;

    if (strpos($userState, 'awaiting_api_key_') === 0) {
        $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
        if (update_env_file($keyToUpdate, $text)) {
            sendTelegramMessage($chatId, "✅ API 密钥 `{$keyToUpdate}` 已成功更新！新配置已生效。", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！", getAdminKeyboard());
        }
        $stateCleared = true;
    } elseif ($userState === 'awaiting_gemini_prompt' || $userState === 'awaiting_cloudflare_prompt') {
        sendTelegramMessage($chatId, "🧠 正在思考中，请稍候...", getAdminKeyboard());
        $response = ($userState === 'awaiting_gemini_prompt') ? call_gemini_api($text) : call_cloudflare_ai_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        $stateCleared = true;
    } elseif ($userState === 'awaiting_user_deletion') {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            if (deleteUserByEmail($text)) {
                sendTelegramMessage($chatId, "✅ 用户 `{$text}` 已成功删除。", getUserManagementKeyboard());
            } else {
                sendTelegramMessage($chatId, "⚠️ 删除失败。用户 `{$text}` 不存在或数据库出错。", getUserManagementKeyboard());
            }
        } else {
            sendTelegramMessage($chatId, "❌ 您输入的不是一个有效的邮箱地址。", getUserManagementKeyboard());
        }
        $stateCleared = true;
    } else {
        $stateCleared = true; // Clear invalid state
        sendTelegramMessage($chatId, "系统状态异常，已重置。", getAdminKeyboard());
    }

    if ($stateCleared) {
        if (setUserState($userId, null) === false) {
            sendTelegramMessage($chatId, "⚠️ **警告:** 无法写入状态文件。");
        }
    }
}

/**
 * Handles stateless command processing (e.g., menu navigation).
 */
function handleCommand($chatId, $userId, $command) {
    $messageToSend = null;
    $keyboard = getAdminKeyboard();

    switch ($command) {
        case '/start':
        case '/':
        case '返回主菜单':
            $messageToSend = "欢迎回来，管理员！请选择一个操作。";
            break;

        case '文件管理':
            $messageToSend = "请选择一个文件管理操作:";
            $keyboard = getFileManagementKeyboard();
            break;

        case '列出文件':
            $files = scandir(__DIR__);
            if ($files === false) {
                $messageToSend = "❌ 无法读取当前目录的文件列表。";
            } else {
                $blacklist = ['.', '..', '.env', '.env.example', '.git', '.gitignore', '.htaccess', 'config.php', 'vendor', 'composer.json', 'composer.lock', 'telegramWebhook.php', 'test_telegram.php'];
                $messageToSend = "📁 **当前目录文件列表:**\n\n```\n";
                $foundFiles = false;
                foreach ($files as $file) {
                    if (!in_array($file, $blacklist, true)) {
                        $messageToSend .= $file . "\n";
                        $foundFiles = true;
                    }
                }
                if (!$foundFiles) $messageToSend .= "(没有可显示的文件)\n";
                $messageToSend .= "```";
            }
            $keyboard = getFileManagementKeyboard();
            break;

        case '用户管理':
            $messageToSend = "请选择一个用户管理操作:";
            $keyboard = getUserManagementKeyboard();
            break;

        case '查看用户列表':
            $users = getAllUsers();
            if (empty($users)) {
                $messageToSend = "数据库中没有找到任何用户。";
            } else {
                $messageToSend = "注册用户列表:\n\n";
                foreach ($users as $user) {
                    $messageToSend .= "📧 **邮箱:** `{$user['email']}`\n📅 **注册于:** {$user['created_at']}\n\n";
                }
            }
            $keyboard = getUserManagementKeyboard();
            break;

        case '删除用户':
            if (setUserState($userId, 'awaiting_user_deletion') === false) {
                $messageToSend = "⚠️ **警告:** 无法写入状态文件。";
            } else {
                $messageToSend = "好的，请发送您想要删除的用户的电子邮件地址。";
                $keyboard = null;
            }
            break;

        case '请求 Gemini':
        case '请求 Cloudflare':
            $state = ($command === '请求 Gemini') ? 'awaiting_gemini_prompt' : 'awaiting_cloudflare_prompt';
            if (setUserState($userId, $state) === false) {
                $messageToSend = "⚠️ **警告:** 无法写入状态文件。";
            } else {
                $messageToSend = "好的，请直接输入您想说的话。";
                $keyboard = null;
            }
            break;

        case '更换 API 密钥':
            $messageToSend = "请选择您想要更新的 API 密钥：";
            $keyboard = getApiKeySelectionKeyboard();
            break;

        case 'Gemini API Key':
            if (setUserState($userId, 'awaiting_api_key_GEMINI_API_KEY') === false) {
                $messageToSend = "⚠️ **警告:** 无法写入状态文件。";
            } else {
                $messageToSend = "好的，请发送您的新 Gemini API 密钥。";
                $keyboard = null;
            }
            break;

        default:
            if (!empty($command)) {
                $messageToSend = "无法识别的指令，请使用下方键盘操作。";
            }
            break;
    }

    if ($messageToSend) {
        sendTelegramMessage($chatId, $messageToSend, $keyboard);
    }
}

// Acknowledge receipt to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);

?>