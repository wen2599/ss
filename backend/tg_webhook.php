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
function addUserToDB($pdo, $username) {
    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        return "用户名无效。它必须是3-32个字符长，并且只能包含字母、数字和下划线。";
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username) VALUES (:username)");
        $stmt->execute([':username' => $username]);
        return "用户 `{$username}` 添加成功。";
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            return "用户 `{$username}` 已存在。";
        }
        error_log("Error adding user: " . $e->getMessage());
        return "添加用户时出错。";
    }
}
function deleteUserFromDB($pdo, $username) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->rowCount() > 0) {
            return "用户 `{$username}` 已被删除。";
        } else {
            return "未找到用户 `{$username}`。";
        }
    } catch (PDOException $e) {
        error_log("Error deleting user: " . $e->getMessage());
        return "删除用户时出错。";
    }
}
function listUsersFromDB($pdo) {
    try {
        $stmt = $pdo->query("SELECT username, email FROM users ORDER BY created_at ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($users)) {
            return "数据库中没有用户。";
        }
        $userList = "👤 *列出所有用户：*\n---------------------\n";
        foreach ($users as $index => $user) {
            $display_name = !empty($user['username']) ? $user['username'] : 'N/A';
            $userList .= ($index + 1) . ". *Email:* `" . htmlspecialchars($user['email']) . "`\n   *Username:* `" . htmlspecialchars($display_name) . "`\n";
        }
        return $userList;
    } catch (PDOException $e) {
        error_log("Error listing users: " . $e->getMessage());
        return "获取用户列表时出错。";
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

// 6. Process the Message
$message = null;
if (isset($update['message'])) {
    $message = $update['message'];
} elseif (isset($update['channel_post'])) {
    $message = $update['channel_post'];
}

if ($message) {
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $admin_id = intval($admin_id);

    // If user is not admin, give a simple rejection and stop.
    if ($chat_id !== $admin_id) {
        sendMessage($chat_id, "您无权使用此机器人。");
        http_response_code(403);
        exit();
    }

    // STATE-BASED INPUT HANDLING
    $admin_state = get_admin_state($chat_id);
    if ($admin_state === 'waiting_for_api_key') {
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
        sendMessage($chat_id, $responseText);
        http_response_code(200);
        exit();
    }

    // LOTTERY RESULT PARSING
    $parsedResult = LotteryParser::parse($text);
    if ($parsedResult) {
        $statusMessage = saveLotteryResultToDB($pdo, $parsedResult);
        $responseText = "成功识别到开奖结果：\n"
                      . "`" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number'] . "`\n\n"
                      . "状态: *" . $statusMessage . "*";
        sendMessage($chat_id, $responseText);
        http_response_code(200);
        exit();
    }

    // COMMAND AND BUTTON HANDLING
    $command_map = [
        '添加用户' => '/adduser',
        '删除用户' => '/deluser',
        '列出所有用户' => '/listusers',
        '分析文本' => '/analyze',
        '⚙️ 设置' => '/settings',
        '⬅️ 返回主菜单' => '/start',
        '设定API密钥' => '/set_gemini_key',
        '检查密钥状态' => '/get_api_key_status',
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
                $responseText = "欢迎回来，管理员！请使用下面的菜单或直接输入命令。";
                $keyboard = json_encode(['keyboard' => [[['text' => '添加用户'], ['text' => '删除用户']], [['text' => '列出所有用户'], ['text' => '⚙️ 设置']]], 'resize_keyboard' => true]);
                break;
            case '/settings':
                $responseText = "⚙️ *设置菜单*\n\n请选择一个操作：";
                $keyboard = json_encode(['keyboard' => [[['text' => '设定API密钥'], ['text' => '检查密钥状态']], [['text' => '⬅️ 返回主菜单']]], 'resize_keyboard' => true]);
                break;
            case '/adduser':
                $responseText = !empty($args) ? addUserToDB($pdo, $args) : "用法：`/adduser <username>`";
                break;
            case '/deluser':
                $responseText = !empty($args) ? deleteUserFromDB($pdo, $args) : "用法：`/deluser <username>`";
                break;
            case '/listusers':
                $responseText = listUsersFromDB($pdo);
                break;
            case '/analyze':
                $responseText = !empty($args) ? analyzeText($args) : "用法：`/analyze <在此处输入您的文本>`";
                break;
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
