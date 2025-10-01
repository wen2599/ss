<?php
/**
 * Telegram Bot Webhook
 *
 * This script acts as the webhook for a Telegram bot.
 * It handles incoming messages and callback queries from Telegram.
 */

// 1. Include Configuration & Libraries
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/LotteryParser.php';
require_once __DIR__ . '/lib/User.php';
require_once __DIR__ . '/lib/Telegram.php';
require_once __DIR__ . '/lib/Lottery.php';

// 2. Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed.']));
}

// 3. Get and Decode the Incoming Update
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);
file_put_contents('webhook_log.txt', $update_json . "\n", FILE_APPEND);


// 4. Process Callback Queries (Button Presses)
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_id = $callback_query['id'];
    $callback_data = $callback_query['data'];
    $admin_chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['id'];
    $original_message_text = $callback_query['message']['text'];

    // Ensure the callback is from the admin
    if ($callback_query['from']['id'] == $admin_id) {
        $parts = explode('_', $callback_data);
        $action = $parts[0];
        $id_type = 'tgid'; // Default for legacy format
        $target_id = null;

        // Handle both new format (approve_dbid_123) and legacy format (approve_12345678)
        if (count($parts) === 3) {
            $id_type = $parts[1]; // 'tgid' or 'dbid'
            $target_id = $parts[2];
        } elseif (count($parts) === 2) {
            $id_type = 'tgid';
            $target_id = $parts[1];
        }

        if (($action === 'approve' || $action === 'deny') && $target_id) {
            $new_status = ($action === 'approve') ? 'approved' : 'denied';

            $id_column = ($id_type === 'dbid') ? 'id' : 'telegram_id';

            $success = User::updateUserStatusById($pdo, $target_id, $id_column, $new_status);

            if ($success) {
                $user_to_notify = User::getUserById($pdo, $target_id, $id_column);
                if ($user_to_notify && !empty($user_to_notify['telegram_id'])) {
                    $user_message = ($new_status === 'approved') ? '您的注册申请已被批准！' : '抱歉，您的注册申请已被拒绝。';
                    Telegram::sendMessage($user_to_notify['telegram_id'], $user_message);
                }

                $status_text = ($new_status === 'approved') ? '已批准' : '已拒绝';
                $new_admin_text = $original_message_text . "\n\n---\n*处理结果: " . $status_text . "*";
                Telegram::editMessageText($admin_chat_id, $message_id, $new_admin_text);
            }
        }
    }
    Telegram::answerCallbackQuery($callback_id);
    http_response_code(200);
    exit();
}


// 5. Process Regular Messages
$message = null;
if (isset($update['message'])) {
    $message = $update['message'];
} elseif (isset($update['channel_post'])) {
    $message = $update['channel_post'];
}

if ($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'] ?? null;
    $text = $message['text'] ?? '';
    $admin_id = intval($admin_id);

    // --- Step 1: Attempt to parse any message as a lottery result first. ---
    $parsedResult = LotteryParser::parse($text);
    if ($parsedResult) {
        $statusMessage = Lottery::saveLotteryResultToDB($pdo, $parsedResult);
        if ($chat_id != $admin_id) {
            $channel_title = isset($message['chat']['title']) ? " from channel \"" . htmlspecialchars($message['chat']['title']) . "\"" : "";
            Telegram::sendMessage($admin_id, "Successfully parsed a new result" . $channel_title . ":\n`" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number'] . "`\n\nStatus: *" . $statusMessage . "*");
        } else {
            Telegram::sendMessage($chat_id, "成功识别到开奖结果：\n`" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number'] . "`\n\n状态: *" . $statusMessage . "*");
        }
        http_response_code(200);
        exit();
    }

    // --- Step 2: Handle public commands like /register before the admin check ---
    if (strpos($text, '/register') === 0) {
        Telegram::sendMessage($chat_id, "您好！请访问我们的网站进行注册。");
        http_response_code(200);
        exit();
    }

    // --- Step 3: If it's not a public command or parsable result, check if the sender is the admin. ---
    if ($user_id !== $admin_id) {
        if ($chat_id === $user_id) {
            Telegram::sendMessage($chat_id, "抱歉，此机器人功能仅限管理员使用。如需注册，请发送 /register。");
        }
        http_response_code(403);
        exit();
    }

    // --- Step 4: Admin-only logic ---
    $main_menu_keyboard = json_encode(['keyboard' => [[['text' => '👤 用户管理'], ['text' => '⚙️ 系统设置']]], 'resize_keyboard' => true]);
    $user_management_keyboard = json_encode(['keyboard' => [[['text' => '📋 列出所有用户'], ['text' => '➖ 删除用户']], [['text' => '⬅️ 返回主菜单']]], 'resize_keyboard' => true]);
    $system_settings_keyboard = json_encode(['keyboard' => [[['text' => '🔑 设定API密钥'], ['text' => 'ℹ️ 检查密钥状态']], [['text' => '⬅️ 返回主菜单']]], 'resize_keyboard' => true]);

    $command_map = [
        '👤 用户管理' => '/user_management',
        '⚙️ 系统设置' => '/system_settings',
        '➖ 删除用户' => '/deluser',
        '📋 列出所有用户' => '/listusers',
        '🔑 设定API密钥' => '/set_gemini_key',
        'ℹ️ 检查密钥状态' => '/get_api_key_status',
        '⬅️ 返回主菜单' => '/start',
    ];

    $command = null;
    $args = '';
    if(isset($command_map[$text])) {
        $command = $command_map[$text];
    } else if (strpos($text, '/') === 0) {
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';
    }

    if ($command) {
        switch ($command) {
            case '/start':
                Telegram::sendMessage($chat_id, "欢迎回来，管理员！请选择一个操作：", $main_menu_keyboard);
                break;
            case '/user_management':
                Telegram::sendMessage($chat_id, "👤 *用户管理*\n\n请选择一个操作：", $user_management_keyboard);
                break;
            case '/system_settings':
                Telegram::sendMessage($chat_id, "⚙️ *系统设置*\n\n请选择一个操作：", $system_settings_keyboard);
                break;
            case '/deluser':
                $responseText = !empty($args) ? User::deleteUserFromDB($pdo, $args) : "用法：`/deluser <telegram_id>`";
                Telegram::sendMessage($chat_id, $responseText);
                break;
            case '/listusers':
                Telegram::sendMessage($chat_id, User::listUsersFromDB($pdo));
                break;
            // Note: The set/get API key logic is removed as it's not fully implemented
            // and was part of the dead code. It can be re-added later if needed.
            default:
                Telegram::sendMessage($chat_id, "抱歉，我不理解该命令。");
                break;
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
?>