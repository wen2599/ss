<?php
// --- Bootstrap and Configuration ---
// This is the only include needed. config.php handles all other helpers.
require_once __DIR__ . '/config.php';

// --- Security and Validation ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$adminId = getenv('TELEGRAM_ADMIN_ID');
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');

$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$receivedParam = $_GET['secret'] ?? null;
$receivedSecret = $receivedHeader ?? $receivedParam;

if (!$expectedSecret || !hash_equals($expectedSecret, $receivedSecret)) {
    http_response_code(403);
    error_log("Webhook rejected: Secret token mismatch or not configured.");
    exit('Forbidden: Secret token mismatch.');
}

// --- Input Processing ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) {
    http_response_code(200); // Acknowledge empty/invalid updates to prevent Telegram retries
    exit();
}

// --- Update Routing ---
$log_entry = "New update received.";

// 1. Channel Post for Lottery Results
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    $chatId = $post['chat']['id'];
    $text = trim($post['text'] ?? '');
    $log_entry .= " Type: channel_post, ChatID: {$chatId}.";

    if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        handleLotteryMessage($text); // Logic is now in lottery_parser.php, included via config.php
        $log_entry .= " Action: Processed as lottery message.";
    } else {
        $log_entry .= " Action: Ignored (not the configured lottery channel).";
    }
    error_log($log_entry);
    http_response_code(200);
    exit();
}

// 2. User Interaction (Message or Callback)
$chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
$userId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;
$command = $update['message']['text'] ?? $update['callback_query']['data'] ?? null;
$callbackQueryId = $update['callback_query']['id'] ?? null;

if ($callbackQueryId) {
    answerTelegramCallbackQuery($callbackQueryId);
}

// --- Authorization and Command Handling ---
if (!$chatId || !$userId || $command === null) {
    error_log("Ignoring update: missing critical fields (chatId, userId, or command).");
    http_response_code(200);
    exit();
}

$log_entry .= " Type: " . ($callbackQueryId ? 'callback_query' : 'message') . ", UserID: {$userId}, Command: '{$command}'.";

// Authorize Admin
if ((string)$userId !== (string)$adminId) {
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    error_log($log_entry . " Action: Denied access (not admin).");
    http_response_code(200);
    exit();
}

// --- Main Logic ---
try {
    $userState = getUserState($userId);
    $cmd = strtolower(trim($command));

    $resetCommands = ['/start', 'main_menu'];

    if (in_array($cmd, $resetCommands)) {
        if ($userState) {
            setUserState($userId, null);
            $log_entry .= " State cleared due to reset command.";
        }
        $userState = null;
    }

    if ($userState) {
        // Handle stateful operations
        $log_entry .= " Handling state '{$userState}'.";

        switch ($userState) {
            case 'awaiting_user_to_delete':
                $userIdToDelete = trim($cmd);
                if (!is_numeric($userIdToDelete)) {
                    $reply = "无效的 ID。请输入一个数字 ID。";
                    $replyKeyboard = getSimpleMainMenuKeyboard();
                } else {
                    if (deleteUserById($userIdToDelete)) {
                        $reply = "成功删除用户 ID: {$userIdToDelete}。";
                    } else {
                        $reply = "无法删除用户 ID: {$userIdToDelete}。可能该 ID 不存在。";
                    }
                    setUserState($userId, null); // Clear state after action
                    $replyKeyboard = getUserManagementKeyboard(); // Go back to user menu
                }
                sendTelegramMessage($chatId, $reply, $replyKeyboard);
                break;

            // TODO: Add cases for other states like 'awaiting_gemini_prompt'

            default:
                // Generic state handler if no specific one is found
                $reply = "您当前处于 '{$userState}' 状态。操作无法识别。点击下方按钮返回主菜单。";
                $replyKeyboard = getSimpleMainMenuKeyboard();
                sendTelegramMessage($chatId, $reply, $replyKeyboard);
                setUserState($userId, null); // Clear unhandled state
                break;
        }

    } else {
        $reply = null;
        $replyKeyboard = null;

        switch ($cmd) {
            case '/start':
            case 'main_menu':
                $reply = "欢迎回来，管理员！";
                $replyKeyboard = getAdminKeyboard();
                break;

            case 'menu_user_management':
                $reply = "请选择用户管理操作：";
                $replyKeyboard = getUserManagementKeyboard();
                break;

            case 'menu_file_management':
                $reply = "请选择文件管理操作：";
                $replyKeyboard = getFileManagementKeyboard();
                break;

            case 'menu_api_keys':
                $reply = "请选择要管理的 API 密钥：";
                $replyKeyboard = getApiKeySelectionKeyboard();
                break;

            case 'ask_gemini':
                setUserState($userId, 'awaiting_gemini_prompt');
                $reply = "请输入您想问 Gemini 的问题：";
                $replyKeyboard = getSimpleMainMenuKeyboard();
                break;

            case 'ask_cloudflare':
                 setUserState($userId, 'awaiting_cloudflare_prompt');
                 $reply = "请输入您想问 Cloudflare AI 的问题：";
                 $replyKeyboard = getSimpleMainMenuKeyboard();
                 break;

            case 'list_users':
                // This will be implemented in the next step.
                // For now, we just acknowledge the command.
                $allUsers = getAllUsers();
                if (empty($allUsers)) {
                    $reply = "数据库中没有找到任何用户。";
                } else {
                    $reply = "<b>注册用户列表:</b>\n\n";
                    foreach ($allUsers as $user) {
                        $reply .= "<b>ID:</b> <code>{$user['id']}</code>\n<b>邮箱:</b> <code>{$user['email']}</code>\n<b>注册于:</b> <code>{$user['created_at']}</code>\n\n";
                    }
                }
                $replyKeyboard = getUserManagementKeyboard(); // Show the menu again
                break;

            case 'delete_user_prompt':
                setUserState($userId, 'awaiting_user_to_delete');
                $reply = "请输入您想删除的用户的 ID。";
                $replyKeyboard = getSimpleMainMenuKeyboard(); // Provide an exit
                break;

            default:
                $reply = "无法识别的命令。";
                $replyKeyboard = getAdminKeyboard();
                break;
        }

        if ($reply) {
            sendTelegramMessage($chatId, $reply, $replyKeyboard);
            $log_entry .= " Action: Processed command '{$cmd}'.";
        } else {
             $log_entry .= " Action: Command '{$cmd}' resulted in no reply.";
        }
    }
} catch (Throwable $e) {
    error_log("FATAL ERROR in webhook: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    sendTelegramMessage($adminId, "机器人内部发生严重错误，请检查日志。");
}

error_log($log_entry);
http_response_code(200);
exit();
?>