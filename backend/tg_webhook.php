<?php
/**
 * Telegram Bot Webhook
 *
 * This script acts as the webhook for a Telegram bot, allowing it to manage users and perform text analysis.
 * It receives updates from Telegram, processes commands, and interacts with a database.
 * Access to management commands is restricted to the admin user specified in config.php.
 */

// 1. Include Configuration using an absolute path
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/LotteryParser.php';

// The authoritative database schema is located in the `data_table_schema.sql` file.

// 2. Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed.']));
}

// 3. Define a function to send messages back to Telegram
function sendMessage($chat_id, $text, $reply_markup = null) {
    global $bot_token;
    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function editMessageText($chat_id, $message_id, $text) {
    global $bot_token;
    $url = "https://api.telegram.org/bot" . $bot_token . "/editMessageText";
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function answerCallbackQuery($callback_query_id) {
    global $bot_token;
    $url = "https://api.telegram.org/bot" . $bot_token . "/answerCallbackQuery";
    $data = ['callback_query_id' => $callback_query_id];
    $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// 4. State Management Functions
function get_admin_state_file($chat_id) {
    return sys_get_temp_dir() . '/tg_admin_state_' . $chat_id . '.txt';
}
function set_admin_state($chat_id, $state) {
    file_put_contents(get_admin_state_file($chat_id), $state);
}
function get_admin_state($chat_id) {
    $file = get_admin_state_file($chat_id);
    return file_exists($file) ? file_get_contents($file) : null;
}
function clear_admin_state($chat_id) {
    $file = get_admin_state_file($chat_id);
    if (file_exists($file)) {
        unlink($file);
    }
}


// 5. User Management and Analysis Functions
function deleteUserFromDB($pdo, $telegram_id) {
    if (!is_numeric($telegram_id)) {
        return "Telegram ID 无效，必须是数字。";
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE telegram_id = :telegram_id");
        $stmt->execute([':telegram_id' => $telegram_id]);
        if ($stmt->rowCount() > 0) {
            return "✅ 用户 ID `{$telegram_id}` 已被删除。";
        } else {
            return "⚠️ 未找到用户 ID `{$telegram_id}`。";
        }
    } catch (PDOException $e) {
        error_log("Error deleting user: " . $e->getMessage());
        return "❌ 删除用户时发生数据库错误。";
    }
}

function listUsersFromDB($pdo) {
    try {
        $stmt = $pdo->query("SELECT telegram_id, username, status FROM users ORDER BY created_at ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($users)) {
            return "数据库中没有用户。";
        }
        $userList = "👤 *所有用户列表:*\n---------------------\n";
        foreach ($users as $index => $user) {
            $username = !empty($user['username']) ? htmlspecialchars($user['username']) : 'N/A';
            $status_icon = '';
            switch ($user['status']) {
                case 'approved':
                    $status_icon = '✅';
                    break;
                case 'pending':
                    $status_icon = '⏳';
                    break;
                case 'denied':
                    $status_icon = '❌';
                    break;
            }
            $userList .= ($index + 1) . ". *" . $username . "*\n"
                      . "   ID: `" . $user['telegram_id'] . "`\n"
                      . "   状态: " . $status_icon . " `" . htmlspecialchars($user['status']) . "`\n";
        }
        return $userList;
    } catch (PDOException $e) {
        error_log("Error listing users: " . $e->getMessage());
        return "获取用户列表时出错。";
    }
}

function updateUserStatus($pdo, $user_id, $status) {
    try {
        $sql = "UPDATE users SET status = :status WHERE telegram_id = :telegram_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':status' => $status, ':telegram_id' => $user_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error updating user status: " . $e->getMessage());
        return false;
    }
}

function registerUser($pdo, $user_id, $username, $admin_id) {
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT status FROM users WHERE telegram_id = :telegram_id");
    $stmt->execute([':telegram_id' => $user_id]);
    $existing_user = $stmt->fetch();

    if ($existing_user) {
        if ($existing_user['status'] === 'approved') {
            return ['status' => 'info', 'message' => '您已经是注册用户。'];
        } elseif ($existing_user['status'] === 'pending') {
            return ['status' => 'info', 'message' => '您的注册申请正在等待批准。'];
        } else { // denied or other statuses
             return ['status' => 'info', 'message' => '您的注册申请已被拒绝。'];
        }
    }

    // Add new user as pending
    try {
        $sql = "INSERT INTO users (telegram_id, username, status) VALUES (:telegram_id, :username, 'pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':telegram_id' => $user_id,
            ':username' => $username
        ]);

        // On successful registration, notify the admin
        $notification_text = "新的用户注册请求：\n"
                           . "---------------------\n"
                           . "*用户:* `" . htmlspecialchars($username) . "`\n"
                           . "*Telegram ID:* `" . $user_id . "`\n"
                           . "---------------------\n"
                           . "请批准或拒绝此请求。";

        $approval_keyboard = json_encode([
            'inline_keyboard' => [[
                ['text' => '✅ 批准', 'callback_data' => 'approve_' . $user_id],
                ['text' => '❌ 拒绝', 'callback_data' => 'deny_' . $user_id]
            ]]
        ]);

        sendMessage($admin_id, $notification_text, $approval_keyboard);

        return ['status' => 'success', 'message' => '您的注册申请已提交，请等待管理员批准。'];
    } catch (PDOException $e) {
        error_log("Error registering user: " . $e->getMessage());
        return ['status' => 'error', 'message' => '注册时出错，请稍后再试。'];
    }
}


/**
 * Saves a parsed lottery result to the database.
 *
 * @param PDO $pdo The database connection object.
 * @param array $result The parsed result array from LotteryParser.
 * @return string A status message indicating the outcome.
 */
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
        } elseif ($rowCount >= 1) { // 2 for update on some drivers
            return "开奖结果已在数据库中更新。";
        } else {
            return "开奖结果与数据库记录一致，未作更改。";
        }
    } catch (PDOException $e) {
        error_log("Database error saving lottery result: " . $e->getMessage());
        return "保存开奖结果时出错。";
    }
}

/**
 * Analyzes a given text and returns a formatted report.
 * @param string $text The text to analyze.
 * @return string The analysis report.
 */
function analyzeText($text) {
    // a. Calculate character count (multi-byte safe)
    $char_count = mb_strlen($text, 'UTF-8');

    // b. Calculate word count (handles punctuation and unicode)
    $cleaned_text_for_words = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $text);
    $word_count = str_word_count($cleaned_text_for_words);

    // c. Extract keywords (long English words and Chinese phrases)
    preg_match_all('/([a-zA-Z]{5,})|([\p{Han}]+)/u', $text, $matches);
    $keywords = array_unique(array_filter($matches[0]));
    $keywords_list = !empty($keywords) ? '`' . implode('`, `', array_values($keywords)) . '`' : '_未找到_';

    // Format the response
    $response = "*文本分析报告：*\n"
              . "---------------------\n"
              . "字符数： *{$char_count}*\n"
              . "单词数： *{$word_count}*\n"
              . "关键词： {$keywords_list}\n";

    return $response;
}


// 5. Get and Decode the Incoming Update
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);
file_put_contents('webhook_log.txt', $update_json . "\n", FILE_APPEND);

// 6. Process the Message or Callback Query
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_id = $callback_query['id'];
    $callback_data = $callback_query['data'];
    $admin_chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['id'];
    $original_message_text = $callback_query['message']['text'];

    // Ensure the callback is from the admin
    global $admin_id;
    if ($callback_query['from']['id'] == $admin_id) {
        list($action, $target_user_id) = explode('_', $callback_data);

        if ($action === 'approve' || $action === 'deny') {
            $new_status = ($action === 'approve') ? 'approved' : 'denied';
            $success = updateUserStatus($pdo, $target_user_id, $new_status);

            if ($success) {
                // Notify the user
                $user_message = ($new_status === 'approved') ? '您的注册申请已被批准！' : '抱歉，您的注册申请已被拒绝。';
                sendMessage($target_user_id, $user_message);

                // Update the admin's original message
                $status_text = ($new_status === 'approved') ? '已批准' : '已拒绝';
                $new_admin_text = $original_message_text . "\n\n---\n*处理结果: " . $status_text . "*";
                editMessageText($admin_chat_id, $message_id, $new_admin_text);
            }
        }
    }
    answerCallbackQuery($callback_id); // Acknowledge the button press
    http_response_code(200);
    exit();
}


$message = null;
if (isset($update['message'])) {
    $message = $update['message'];
} elseif (isset($update['channel_post'])) {
    $message = $update['channel_post'];
}

if ($message) {
    $chat_id = $message['chat']['id'];
    // Use the sender's ID for admin checks. For channel posts, 'from' is not set, so user_id will be null.
    $user_id = $message['from']['id'] ?? null;
    $text = $message['text'] ?? '';
    $admin_id = intval($admin_id);

    // --- Step 1: Attempt to parse any message as a lottery result first. ---
    // This allows the bot to process results from channels where it's a member,
    // without needing any admin permissions for that action.
    $parsedResult = LotteryParser::parse($text);
    if ($parsedResult) {
        $statusMessage = saveLotteryResultToDB($pdo, $parsedResult);

        // Notify the admin that a result was parsed from a channel, but don't spam the channel.
        if ($chat_id != $admin_id) {
            $channel_title = isset($message['chat']['title']) ? " a \"" . htmlspecialchars($message['chat']['title']) . "\"" : "";
            sendMessage($admin_id, "成功从频道" . $channel_title . "识别到新的开奖结果:\n`" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number'] . "`\n\n状态: *" . $statusMessage . "*");
        } else {
            // If the admin sent the result directly, reply to the admin.
            sendMessage($chat_id, "成功识别到开奖结果：\n`" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number'] . "`\n\n状态: *" . $statusMessage . "*");
        }
        http_response_code(200);
        exit();
    }

    // --- Step 2: Handle public commands like /register before the admin check ---
    if (strpos($text, '/register') === 0) {
        if ($user_id && $chat_id === $user_id) { // Ensure it's a direct message from a user
            $username = $message['from']['username'] ?? ($message['from']['first_name'] ?? 'N/A');
            $reg_result = registerUser($pdo, $user_id, $username, $admin_id);

            sendMessage($chat_id, $reg_result['message']);

        } else if ($chat_id !== $user_id) {
            // Instruct user to message privately if they try to register in a group
            sendMessage($chat_id, "请在与机器人的私聊中发送 /register 命令来注册。");
        }
        http_response_code(200);
        exit();
    }


    // --- Step 3: If it's not a public command or parsable result, check if the sender is the admin. ---
    // All following actions (commands, stateful conversations) are admin-only.
    if ($user_id !== $admin_id) {
        // Silently ignore non-admin messages in groups/channels that are not lottery results.
        // Only send a "no permission" message if a user is messaging the bot directly.
        if ($chat_id === $user_id) {
            sendMessage($chat_id, "抱歉，此机器人功能仅限管理员使用。");
        }
        http_response_code(403); // Forbidden
        exit();
    }

    // --- Step 4: Admin-only logic (commands and stateful conversations) ---

    // Define keyboard layouts first, as they are used in state handling replies
    $main_menu_keyboard = json_encode(['keyboard' => [[['text' => '👤 用户管理'], ['text' => '⚙️ 系统设置']]], 'resize_keyboard' => true]);
    $user_management_keyboard = json_encode(['keyboard' => [[['text' => '📋 列出所有用户'], ['text' => '➖ 删除用户']], [['text' => '⬅️ 返回主菜单']]], 'resize_keyboard' => true]);
    $system_settings_keyboard = json_encode(['keyboard' => [[['text' => '🔑 设定API密钥'], ['text' => 'ℹ️ 检查密钥状态']], [['text' => '⬅️ 返回主菜单']]], 'resize_keyboard' => true]);

    // STATE-BASED INPUT HANDLING
    $raw_state = get_admin_state($chat_id);
    $state_data = $raw_state ? json_decode($raw_state, true) : null;
    $current_state = $state_data['state'] ?? ($raw_state ?: null);

    // Universal command-based cancellation for any stateful operation
    if (strpos($text, '/') === 0 && $current_state) {
        clear_admin_state($chat_id);
        $current_state = null; // Unset state to proceed to normal command handling
        sendMessage($chat_id, "操作已取消。");
    }

    if ($current_state) {
        // Handle legacy string-based state for API key
        if ($current_state === 'waiting_for_api_key') {
            try {
                $sql = "UPDATE application_settings SET setting_value = :api_key WHERE setting_name = 'gemini_api_key'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':api_key' => $text]);
                $responseText = "✅ Gemini API密钥已成功更新。";
            } catch (PDOException $e) {
                error_log("Error updating Gemini API key: " . $e->getMessage());
                $responseText = "❌ 更新Gemini API密钥时发生数据库错误。";
            }
            clear_admin_state($chat_id);
            sendMessage($chat_id, $responseText, $system_settings_keyboard);
            exit();
        }
    }

    // COMMAND AND BUTTON HANDLING
    $command_map = [
        // Main Menu
        '👤 用户管理' => '/user_management',
        '⚙️ 系统设置' => '/system_settings',
        // User Management
        '➖ 删除用户' => '/deluser',
        '📋 列出所有用户' => '/listusers',
        // System Settings
        '🔑 设定API密钥' => '/set_gemini_key',
        'ℹ️ 检查密钥状态' => '/get_api_key_status',
        // Common
        '⬅️ 返回主菜单' => '/start',
        // Legacy/Hidden commands for direct invocation
        '分析文本' => '/analyze',
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
            // Main Menus
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

            // User Management Actions
            case '/deluser':
                $responseText = !empty($args) ? deleteUserFromDB($pdo, $args) : "用法：`/deluser <telegram_id>`";
                break;
            case '/listusers':
                $responseText = listUsersFromDB($pdo);
                break;

            // System Settings Actions
            case '/set_gemini_key':
                if (!empty($args)) { // Direct command with key
                    try {
                        $sql = "UPDATE application_settings SET setting_value = :api_key WHERE setting_name = 'gemini_api_key'";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':api_key' => $args]);
                        $responseText = "✅ Gemini API密钥已成功更新。";
                    } catch (PDOException $e) {
                        $responseText = "❌ 更新时发生数据库错误。";
                    }
                } else { // Interactive mode
                    set_admin_state($chat_id, 'waiting_for_api_key');
                    $responseText = "请直接粘贴您的Gemini API密钥并发送。";
                }
                break;
            case '/get_api_key_status':
                try {
                    $sql = "SELECT setting_value FROM application_settings WHERE setting_name = 'gemini_api_key'";
                    $stmt = $pdo->query($sql);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && !empty($result['setting_value']) && $result['setting_value'] !== 'YOUR_GEMINI_API_KEY') {
                        $responseText = "ℹ️ Gemini API密钥状态: *已设置*。";
                    } else {
                        $responseText = "⚠️ Gemini API密钥状态: *未设置*。";
                    }
                } catch (PDOException $e) {
                    $responseText = "❌ 检查API密钥状态时发生数据库错误。";
                }
                break;

            // Legacy/Hidden Actions
            case '/analyze':
                $responseText = !empty($args) ? analyzeText($args) : "用法：`/analyze <在此处输入您的文本>`";
                break;

            default:
                $responseText = "抱歉，我不理解该命令。";
                break;
        }
        sendMessage($chat_id, $responseText, $keyboard ?? null);
    }
}

// 7. Respond to Telegram to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
