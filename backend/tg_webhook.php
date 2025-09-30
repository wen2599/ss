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

function generatePatternFromExample($data) {
    if (empty($data['full_example']) || empty($data['lottery_name_part']) || empty($data['issue_number_part']) || empty($data['numbers_part'])) {
        return null; // Not enough data
    }

    $full_example = $data['full_example'];
    $name_part = $data['lottery_name_part'];
    $issue_part = $data['issue_number_part'];
    $numbers_part = $data['numbers_part'];

    // Check that all provided parts actually exist in the full example
    if (strpos($full_example, $name_part) === false ||
        strpos($full_example, $issue_part) === false ||
        strpos($full_example, $numbers_part) === false) {
        return null; // Parts don't match the example
    }

    // Escape the whole example to treat it as a literal string
    $pattern = preg_quote($full_example, '/');

    // Replace the user-provided parts with flexible capture groups
    $pattern = str_replace(preg_quote($name_part, '/'), '(.*)', $pattern, $count1);
    $pattern = str_replace(preg_quote($issue_part, '/'), '(\d+)', $pattern, $count2);
    $pattern = str_replace(preg_quote($numbers_part, '/'), '([\\d,\\s+-]+)', $pattern, $count3);

    if ($count1 !== 1 || $count2 !== 1 || $count3 !== 1) {
        return null; // Something went wrong, maybe duplicate parts
    }

    $pattern = preg_replace('/\s+/', '\s+', $pattern);

    return '/' . $pattern . '/u';
}

function saveTemplateToDB($pdo, $templateData) {
    if (empty($templateData['pattern']) || empty($templateData['type'])) {
        return "模板格式或类型不能为空。";
    }
    if (!is_numeric($templateData['priority'])) {
        return "优先级必须是一个数字。";
    }
    if (@preg_match($templateData['pattern'], '') === false) {
        return "提供的格式不是有效的正则表达式。";
    }

    $sql = "INSERT INTO parsing_templates (pattern, type, priority, description) VALUES (:pattern, :type, :priority, :description)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':pattern' => $templateData['pattern'],
            ':type' => $templateData['type'],
            ':priority' => (int)$templateData['priority'],
            ':description' => 'User-provided template via bot'
        ]);
        return "✅ 模板已成功保存。 ID: " . $pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            return "❌ 错误：具有该格式的模板已存在。";
        }
        error_log("Database error saving template: " . $e->getMessage());
        return "❌ 保存模板时发生数据库错误。";
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
    // Use the sender's ID for admin checks. For channel posts, 'from' is not set, so user_id will be null.
    $user_id = $message['from']['id'] ?? null;
    $text = $message['text'] ?? '';
    $admin_id = intval($admin_id);

    // --- Step 1: Attempt to parse any message as a lottery result first. ---
    // This allows the bot to process results from channels where it's a member,
    // without needing any admin permissions for that action.
    $parsedResult = LotteryParser::parse($text, $pdo);
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

    // --- Step 2: If it's not a parsable result, check if the sender is the admin. ---
    // All following actions (commands, stateful conversations) are admin-only.
    if ($user_id !== $admin_id) {
        // Only send a "no permission" message if a user is messaging the bot directly.
        // This prevents the bot from replying to random, non-result messages in channels.
        if ($chat_id === $user_id) {
            sendMessage($chat_id, "您无权使用此机器人。");
        }
        http_response_code(403); // Forbidden
        exit();
    }

    // --- Step 3: Admin-only logic (commands and stateful conversations) ---

    // Define keyboard layouts first, as they are used in state handling replies
    $main_menu_keyboard = json_encode(['keyboard' => [[['text' => '👤 用户管理'], ['text' => '📝 模板管理']], [['text' => '⚙️ 系统设置']]], 'resize_keyboard' => true]);
    $user_management_keyboard = json_encode(['keyboard' => [[['text' => '➕ 添加用户'], ['text' => '➖ 删除用户']], [['text' => '📋 列出所有用户']], [['text' => '⬅️ 返回主菜单']]], 'resize_keyboard' => true]);
    $template_management_keyboard = json_encode(['keyboard' => [[['text' => '➕ 添加新模板']], [['text' => '⬅️ 返回主菜单']]], 'resize_keyboard' => true]);
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

        // Handle JSON-based state machine for "Teach by Example"
        switch ($current_state) {
            case 'waiting_for_full_example':
                $state_data['data']['full_example'] = $text;
                $state_data['state'] = 'waiting_for_lottery_name_part';
                set_admin_state($chat_id, json_encode($state_data));
                sendMessage($chat_id, "✅ 示例已收到。\n\n*步骤 2/4:* 现在，请从您的示例中复制并发送 *开奖名称*。");
                exit();

            case 'waiting_for_lottery_name_part':
                $state_data['data']['lottery_name_part'] = $text;
                $state_data['state'] = 'waiting_for_issue_number_part';
                set_admin_state($chat_id, json_encode($state_data));
                sendMessage($chat_id, "✅ 名称已收到。\n\n*步骤 3/4:* 现在，请从您的示例中复制并发送 *期号*。");
                exit();

            case 'waiting_for_issue_number_part':
                $state_data['data']['issue_number_part'] = $text;
                $state_data['state'] = 'waiting_for_numbers_part';
                set_admin_state($chat_id, json_encode($state_data));
                sendMessage($chat_id, "✅ 期号已收到。\n\n*步骤 4/4:* 最后，请从您的示例中复制并发送包含所有7个号码的 *那一部分文本*。");
                exit();

            case 'waiting_for_numbers_part':
                $state_data['data']['numbers_part'] = $text;

                $generated_pattern = generatePatternFromExample($state_data['data']);

                if ($generated_pattern) {
                    $newTemplateData = [
                        'pattern' => $generated_pattern,
                        'type' => 'lottery_result',
                        'priority' => 90 // High priority for user-taught templates
                    ];
                    $responseText = saveTemplateToDB($pdo, $newTemplateData);
                } else {
                    $responseText = "❌ 模板生成失败。请确保您提供的所有部分都与原始示例完全匹配且不重复。";
                }

                clear_admin_state($chat_id);
                sendMessage($chat_id, $responseText, $template_management_keyboard);
                exit();
        }
    }

    // COMMAND AND BUTTON HANDLING
    $command_map = [
        // Main Menu
        '👤 用户管理' => '/user_management',
        '📝 模板管理' => '/template_management',
        '⚙️ 系统设置' => '/system_settings',
        // User Management
        '➕ 添加用户' => '/adduser',
        '➖ 删除用户' => '/deluser',
        '📋 列出所有用户' => '/listusers',
        // Template Management
        '➕ 添加新模板' => '/add_template',
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
            case '/template_management':
                $responseText = "📝 *模板管理*\n\n请选择一个操作：";
                $keyboard = $template_management_keyboard;
                break;
            case '/system_settings':
                $responseText = "⚙️ *系统设置*\n\n请选择一个操作：";
                $keyboard = $system_settings_keyboard;
                break;

            // User Management Actions
            case '/adduser':
                $responseText = !empty($args) ? addUserToDB($pdo, $args) : "用法：`/adduser <username>`";
                break;
            case '/deluser':
                $responseText = !empty($args) ? deleteUserFromDB($pdo, $args) : "用法：`/deluser <username>`";
                break;
            case '/listusers':
                $responseText = listUsersFromDB($pdo);
                break;

            // Template Management Actions
            case '/add_template':
                $state_payload = json_encode(['state' => 'waiting_for_full_example', 'data' => []]);
                set_admin_state($chat_id, $state_payload);
                $responseText = "*步骤 1/4:* 请粘贴一个您想让机器人学习的 *完整* 开奖信息示例。\n\n发送 `/start` 可随时取消。";
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
