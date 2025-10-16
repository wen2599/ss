<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/db_operations.php'; // Ensure database operations are available

// --- Debugging Request Headers and Config ---
error_log("------ Webhook Entry Point ------");
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '[Not Provided]';
error_log("Received Secret Token Header: " . $receivedToken);
$loadedSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
error_log("Loaded TELEGRAM_WEBHOOK_SECRET: " . ($loadedSecret ? '***' : '[Not Loaded]'));
$loadedAdminId = getenv('TELEGRAM_ADMIN_ID');
error_log("Loaded TELEGRAM_ADMIN_ID: " . ($loadedAdminId ? '***' : '[Not Loaded]'));
$loadedLotteryChannelId = getenv('LOTTERY_CHANNEL_ID'); // Load Lottery Channel ID
error_log("Loaded LOTTERY_CHANNEL_ID: " . ($loadedLotteryChannelId ? '***' : '[Not Loaded]'));
error_log("-------------------------------");


// --- Security Validation ---
$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

error_log("Webhook Security Check: Received Token - '{$receivedToken}', Expected Token - '" . ($secretToken ? 'SET' : 'NOT SET') . "'");

if (!$secretToken || $receivedToken !== $secretToken) {
    error_log("Webhook Forbidden: Token mismatch. Received: '{$receivedToken}', Expected: '" . ($secretToken ? 'SET' : 'NOT SET') . "'");
    http_response_code(403);
    exit('Forbidden: Secret token mismatch.');
}

// --- Main Webhook Logic ---
$update = json_decode(file_get_contents('php://input'), true);

$chatId = null;
$userId = null;
$command = null;

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

    // --- NEW: Handle Lottery Channel Messages ---
    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
    if ($lotteryChannelId && (string)$chatId === (string)$lotteryChannelId) {
        error_log("Received message from lottery channel: " . $chatId . " with text: " . $command);
        handleLotteryMessage($chatId, $command);
        http_response_code(200); // Acknowledge and exit, as it's handled
        echo json_encode(['status' => 'ok', 'message' => 'Lottery message processed.']);
        exit();
    }

} else {
    // If it's not a message or a callback query we recognize, ignore it.
    error_log("Ignoring unsupported update type: " . json_encode($update));
    http_response_code(200); // Acknowledge and ignore
    exit();
}

// If chat ID or user ID are still null, it's an unhandled update type for admin logic
if ($chatId === null || $userId === null) {
    error_log("Webhook: Chat ID or User ID is null, possibly unhandled update type after lottery check.");
    http_response_code(200); // Still acknowledge, just log
    exit();
}

// --- Admin Verification ---
// The admin ID is now fetched using getenv() for consistency
$adminChatId = getenv('TELEGRAM_ADMIN_ID');
if (empty($adminChatId) || (string)$chatId !== (string)$adminChatId) {
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    http_response_code(200); // Acknowledge and exit
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

/**
 * Handles messages from the lottery channel, parses them, and stores results.
 * Expected message format (example):
 * "【六合彩】第2023001期开奖结果：
 * 号码：01 02 03 04 05 06 特 07
 * 生肖：鼠 牛 虎 龙 蛇 马
 * 颜色：红 红 蓝 蓝 绿 绿 特红
 * 开奖日期：2023-10-26"
 *
 * @param int $chatId The ID of the chat where the message originated.
 * @param string $messageText The text content of the message.
 */
function handleLotteryMessage($chatId, $messageText) {
    error_log("Attempting to parse lottery message: " . $messageText);

    $lottery_type = '未知彩票';
    $issue_number = '';
    $winning_numbers = '';
    $zodiac_signs = '';
    $colors = '';
    $drawing_date = date('Y-m-d'); // Default to today's date

    // Regex to extract lottery type and issue number
    if (preg_match('/【(.*?)】第(\d+)期开奖结果/', $messageText, $matches)) {
        $lottery_type = trim($matches[1]);
        $issue_number = trim($matches[2]);
    }

    // Regex to extract winning numbers
    if (preg_match('/号码[：:]\s*(.*?)(?:\s+特\s*(\d+))?/', $messageText, $matches)) {
        $numbers = trim($matches[1]);
        if (isset($matches[2]) && !empty($matches[2])) {
            $numbers .= ' ' . trim($matches[2]);
        }
        $winning_numbers = preg_replace('/\s+/', ' ', $numbers); // Normalize spaces
    }

    // Regex to extract zodiac signs
    if (preg_match('/生肖[：:]\s*(.*)/', $messageText, $matches)) {
        $zodiac_signs = trim($matches[1]);
    }

    // Regex to extract colors
    if (preg_match('/颜色[：:]\s*(.*)/', $messageText, $matches)) {
        $colors = trim($matches[1]);
    }

    // Regex to extract drawing date
    if (preg_match('/开奖日期[：:]\s*(\d{4}-\d{2}-\d{2})/', $messageText, $matches)) {
        $drawing_date = trim($matches[1]);
    }

    // Store the results in the database
    $result = storeLotteryResult(
        $lottery_type,
        $issue_number,
        $winning_numbers,
        $zodiac_signs,
        $colors,
        $drawing_date
    );

    if ($result) {
        error_log("Lottery result for {$lottery_type} {$issue_number} stored successfully.");
        // Optionally, send a confirmation to the admin if needed, but not to the channel itself
        // sendTelegramMessage(getenv('TELEGRAM_ADMIN_ID'), "✅ 已成功存储 {$lottery_type} 第{$issue_number}期开奖结果。");
    } else {
        error_log("Failed to store lottery result for {$lottery_type} {$issue_number}.");
        // Optionally, send an error notification to the admin
        // sendTelegramMessage(getenv('TELEGRAM_ADMIN_ID'), "❌ 存储 {$lottery_type} 第{$issue_number}期开奖结果失败！请检查日志。");
    }
}


// Acknowledge receipt to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);

?>