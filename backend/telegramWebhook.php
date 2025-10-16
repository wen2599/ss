<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram_helpers.php';

// --- Debugging Request Headers and Config --- 
error_log("------ Webhook Request Debug Start ------");
error_log("All Headers: " . json_encode(getallheaders()));
error_log("Config TELEGRAM_WEBHOOK_SECRET: '" . ($TELEGRAM_WEBHOOK_SECRET ?? 'NOT SET') . "'");
error_log("Received TELEGRAM_BOT_TOKEN: '" . ($TELEGRAM_BOT_TOKEN ?? 'NOT SET') . "'");
error_log("------ Webhook Request Debug End ------");


// --- Security Validation ---
// Use getenv() for consistency with the rest of the application
$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

// --- Detailed Debugging ---
error_log("Webhook Security Check: Received Token - '{$receivedToken}', Expected Token - '" . ($secretToken ? 'SET' : 'NOT SET') . "'");

if (!$secretToken || $receivedToken !== $secretToken) {
    error_log("Webhook Forbidden: Token mismatch. Received: '{$receivedToken}', Expected: '" . ($secretToken ? 'SET' : 'NOT SET') . "'");
    http_response_code(403);
    exit('Forbidden: Secret token mismatch.');
}

// --- Main Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);

// Check for different update types (message vs. button press)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $callbackQuery['from']['id'];
    $command = $callbackQuery['data']; // The 'data' field from the inline button

    // Acknowledge the button press to remove the loading animation
    answerTelegramCallbackQuery($callbackQuery['id']);

} elseif (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'] ?? $chatId;
    $command = trim($message['text'] ?? '');

} else {
    // If it's not a message or a callback query we recognize, ignore it.
    error_log("Ignoring unsupported update type: " . json_encode($update));
    exit();
}


// --- Admin Verification ---
// The admin ID is now fetched using getenv() for consistency
$adminChatId = getenv('TELEGRAM_ADMIN_ID');
if (empty($adminChatId) || (string)$chatId !== (string)$adminChatId) {
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

// --- Process the Command ---
processCommand($chatId, $userId, $command);

// ... (rest of the file remains the same) ...

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
    $keyboard = null;

    switch ($command) {
        // Main Menu Navigation
        case '/start':
        case 'main_menu':
            $messageToSend = "欢迎回来，管理员！请选择一个操作。";
            $keyboard = getAdminKeyboard();
            break;

        // Sub-menu Navigation
        case 'menu_user_management':
            $messageToSend = "请选择一个用户管理操作:";
            $keyboard = getUserManagementKeyboard();
            break;
        case 'menu_file_management':
            $messageToSend = "请选择一个文件管理操作:";
            $keyboard = getFileManagementKeyboard();
            break;
        case 'menu_api_keys':
            $messageToSend = "请选择您想要更新的 API 密钥：";
            $keyboard = getApiKeySelectionKeyboard();
            break;

        // Actions
        case 'list_files':
            $files = scandir(__DIR__);
            $messageToSend = "📁 **当前目录文件列表:**\n\n`";
            $blacklist = ['.', '..', '.env', '.env.example', '.git', '.gitignore', '.htaccess', 'vendor', 'composer.lock', 'debug.log'];
            foreach ($files as $file) {
                if (!in_array($file, $blacklist, true)) $messageToSend .= $file . "\n";
            }
            $messageToSend .= "`";
            $keyboard = getFileManagementKeyboard();
            break;

        case 'list_users':
            $users = getAllUsers();
            if (empty($users)) {
                $messageToSend = "数据库中没有找到任何用户。";
            } else {
                $messageToSend = "👥 **注册用户列表:**\n\n";
                foreach ($users as $user) {
                    $messageToSend .= "📧 **邮箱:** `{$user['email']}`\n" .
                                      "📅 **注册于:** `{$user['created_at']}`\n\n";
                }
            }
            $keyboard = getUserManagementKeyboard();
            break;

        case 'delete_user_prompt':
            setUserState($userId, 'awaiting_user_deletion');
            $messageToSend = "好的，请发送您想要删除的用户的电子邮件地址。";
            // No keyboard, awaiting text input
            break;

        case 'ask_gemini':
        case 'ask_cloudflare':
            $state = ($command === 'ask_gemini') ? 'awaiting_gemini_prompt' : 'awaiting_cloudflare_prompt';
            setUserState($userId, $state);
            $messageToSend = "好的，请直接输入您想说的话。";
            // No keyboard, awaiting text input
            break;

        case strpos($command, 'set_api_key_') === 0:
            $keyToSet = substr($command, strlen('set_api_key_'));
            setUserState($userId, 'awaiting_api_key_' . $keyToSet);
            $messageToSend = "好的，请发送您的新 `{$keyToSet}`。";
            // No keyboard, awaiting text input
            break;

        // Default case for unrecognized text commands
        default:
            // Only show an error for non-empty text commands that are not recognized.
            // Callback queries that don't match will be ignored silently.
            if (!empty($command) && !isset($update['callback_query'])) {
                 $messageToSend = "无法识别的指令 `{$command}`，请使用下方键盘操作。";
                 $keyboard = getAdminKeyboard();
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