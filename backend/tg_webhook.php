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

// SQL to create the necessary table:
/*
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
*/

// 2. Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
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

// 4. User Management and Analysis Functions
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
        $stmt = $pdo->query("SELECT username FROM users ORDER BY created_at ASC");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($users)) {
            return "数据库中没有用户。";
        }
        $userList = "列出所有用户：\n";
        foreach ($users as $index => $user) {
            $userList .= ($index + 1) . ". `" . htmlspecialchars($user) . "`\n";
        }
        return $userList;
    } catch (PDOException $e) {
        error_log("Error listing users: " . $e->getMessage());
        return "获取用户列表时出错。";
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
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];
    $admin_id = intval($admin_id);

    // First, try to parse the message as a lottery result
    $parsedResult = LotteryParser::parse($text);

    if ($parsedResult) {
        // If parsing is successful, log the result.
        // Later, this will be saved to the database.
        $logMessage = "Parsed Lottery Result: " . json_encode($parsedResult, JSON_UNESCAPED_UNICODE);
        file_put_contents('webhook_log.txt', $logMessage . "\n", FILE_APPEND);
        // Optionally, send a confirmation back to the admin
        if ($chat_id === $admin_id) {
            sendMessage($chat_id, "成功识别到开奖结果：\n" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number']);
        }
    } else {
        // If it's not a lottery result, process it as a command
        $command_map = [
            '添加用户' => '/adduser',
            '删除用户' => '/deluser',
            '列出所有用户' => '/listusers',
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
            if ($command === '/start') {
                $responseText = "欢迎！我是您的用户管理机器人。\n\n";
                $keyboard = null;
                if ($chat_id === $admin_id) {
                    $responseText .= "您是管理员。请使用下面的菜单或直接输入命令。";
                    $keyboard_buttons = [
                        [['text' => '添加用户'], ['text' => '删除用户']],
                        [['text' => '列出所有用户'], ['text' => '分析文本']]
                    ];
                    $keyboard = json_encode(['keyboard' => $keyboard_buttons, 'resize_keyboard' => true, 'one_time_keyboard' => false]);
                } else {
                    $responseText .= "您无权执行任何操作。";
                }
                sendMessage($chat_id, $responseText, $keyboard);
                http_response_code(200);
                exit();
            }

            if ($chat_id !== $admin_id) {
                sendMessage($chat_id, "您无权使用此命令。");
                http_response_code(403);
                exit();
            }

            switch ($command) {
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
                default:
                    $responseText = "抱歉，我不理解该命令。";
                    break;
            }
            sendMessage($chat_id, $responseText);
        }
        // If it's not a lottery result and not a command, do nothing.
        // We don't want to reply to every single message in the channel.
    }
}

// 7. Respond to Telegram to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
