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
        $action = $parts[0]; // 'approve' or 'deny'
        $id_type = $parts[1]; // 'tgid' or 'dbid'
        $target_id = $parts[2];

        if (($action === 'approve' || $action === 'deny') && in_array($id_type, ['tgid', 'dbid'])) {
            $new_status = ($action === 'approve') ? 'approved' : 'denied';

            // Determine which column to use for the update
            $id_column = ($id_type === 'tgid') ? 'telegram_id' : 'id';

            // Update user status
            $sql = "UPDATE users SET status = :status WHERE {$id_column} = :id";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([':status' => $new_status, ':id' => $target_id]);

            if ($success) {
                // Fetch the user's telegram_id to notify them, if available
                $user_stmt = $pdo->prepare("SELECT telegram_id FROM users WHERE {$id_column} = :id");
                $user_stmt->execute([':id' => $target_id]);
                $user_to_notify = $user_stmt->fetch();

                if ($user_to_notify && $user_to_notify['telegram_id']) {
                    $user_message = ($new_status === 'approved') ? '您的注册申请已被批准！' : '抱歉，您的注册申请已被拒绝。';
                    Telegram::sendMessage($user_to_notify['telegram_id'], $user_message);
                }

                // Update the admin's original message
                $status_text = ($new_status === 'approved') ? '已批准' : '已拒绝';
                $new_admin_text = $original_message_text . "\n\n---\n*处理结果: " . $status_text . "*";
                Telegram::editMessageText($admin_chat_id, $message_id, $new_admin_text);
            }
        }
    }
    Telegram::answerCallbackQuery($callback_id); // Acknowledge the button press
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
        // This function is defined in User.php, but it's about lottery results, so let's move it here for clarity.
        function saveLotteryResultToDB($pdo, $result) {
            $numbers_str = implode(',', $result['numbers']);
            $sql = "INSERT INTO lottery_results (lottery_name, issue_number, numbers)
                    VALUES (:lottery_name, :issue_number, :numbers)
                    ON DUPLICATE KEY UPDATE numbers = VALUES(numbers), parsed_at = CURRENT_TIMESTAMP";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':lottery_name' => $result['lottery_name'],
                    ':issue_number' => $result['issue_number'],
                    ':numbers' => $numbers_str
                ]);
                $rowCount = $stmt->rowCount();
                if ($rowCount === 1) {
                    return "新开奖结果已成功存入数据库。";
                } elseif ($rowCount >= 1) {
                    return "开奖结果已在数据库中更新。";
                } else {
                    return "开奖结果与数据库记录一致，未作更改。";
                }
            } catch (PDOException $e) {
                error_log("Database error saving lottery result: " . $e->getMessage());
                return "保存开奖结果时出错。";
            }
        }
        $statusMessage = saveLotteryResultToDB($pdo, $parsedResult);
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
        http_response_code(403); // Forbidden
        exit();
    }

    // --- Step 4: Admin-only logic (commands and stateful conversations) ---
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

    if (isset($command_map[$text])) {
        $command = $command_map[$text];
    } elseif (strpos($text, '/') === 0) {
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = isset($parts[1]) ? trim($parts[1]) : '';
    }

    if ($command) {
        switch ($command) {
            case '/start':
                $responseText = "欢迎回来，管理员！请选择一个操作：";
                $keyboard = $main_menu_keyboard;
                break;
            case '/user_management':
                $responseText = "👤 *用户管理*\n\n请选择一个操作：";
                $keyboard = $user_management_keyboard;
                break;
            case '/system_settings':
                $responseText = "⚙️ *系统设置*\n\n请选择一个操作：";
                $keyboard = $system_settings_keyboard;
                break;
            case '/deluser':
                $responseText = !empty($args) ? User::deleteUserFromDB($pdo, $args) : "用法：`/deluser <telegram_id>`";
                break;
            case '/listusers':
                $responseText = User::listUsersFromDB($pdo);
                break;
            case '/set_gemini_key':
                 if (!empty($args)) {
                    // Logic to update key
                 } else {
                    // Logic to prompt for key
                 }
                break;
            case '/get_api_key_status':
                // Logic to check key status
                break;
            default:
                $responseText = "抱歉，我不理解该命令。";
                break;
        }
        Telegram::sendMessage($chat_id, $responseText, $keyboard ?? null);
    }
}

// 7. Respond to Telegram to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>