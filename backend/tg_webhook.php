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

function listTemplatesFromDB($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, pattern, type, priority FROM parsing_templates ORDER BY priority ASC, id ASC");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($templates)) {
            return "数据库中没有自定义模板。";
        }
        $templateList = "📝 *所有解析模板:*\n---------------------\n";
        foreach ($templates as $template) {
            $templateList .= "*ID:* `" . $template['id'] . "`\n"
                          . "*类型:* `" . htmlspecialchars($template['type']) . "`\n"
                          . "*优先级:* `" . $template['priority'] . "`\n"
                          . "*格式:* `" . htmlspecialchars($template['pattern']) . "`\n"
                          . "---------------------\n";
        }
        return $templateList;
    } catch (PDOException $e) {
        error_log("Error listing templates: " . $e->getMessage());
        return "获取模板列表时出错。";
    }
}

function deleteTemplateFromDB($pdo, $template_id) {
    if (!is_numeric($template_id)) {
        return "模板ID必须是一个数字。";
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM parsing_templates WHERE id = :id");
        $stmt->execute([':id' => $template_id]);
        if ($stmt->rowCount() > 0) {
            return "✅ 模板 ID `{$template_id}` 已被删除。";
        } else {
            return "⚠️ 未找到模板 ID `{$template_id}`。";
        }
    } catch (PDOException $e) {
        error_log("Error deleting template: " . $e->getMessage());
        return "❌ 删除模板时发生数据库错误。";
    }
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
    $text = $message['text'] ?? '';
    $admin_id = intval($admin_id);

    // If user is not admin, give a simple rejection and stop.
    if ($chat_id !== $admin_id) {
        sendMessage($chat_id, "您无权使用此机器人。");
        http_response_code(403);
        exit();
    }

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

        // Handle JSON-based state machine for adding templates
        switch ($current_state) {
            case 'waiting_for_template_pattern':
                $state_data['data']['pattern'] = $text;
                $state_data['state'] = 'waiting_for_template_type';
                set_admin_state($chat_id, json_encode($state_data));
                sendMessage($chat_id, "✅ 格式已保存。\n\n2/3: 现在，请输入模板类型 (例如: `lottery_result`)。");
                exit();

            case 'waiting_for_template_type':
                $state_data['data']['type'] = $text;
                $state_data['state'] = 'waiting_for_template_priority';
                set_admin_state($chat_id, json_encode($state_data));
                sendMessage($chat_id, "✅ 类型已保存。\n\n3/3: 现在，请输入模板的优先级 (数字, 越小越高, 默认 100)。");
                exit();

            case 'waiting_for_template_priority':
                $state_data['data']['priority'] = $text;
                $responseText = saveTemplateToDB($pdo, $state_data['data']);
                clear_admin_state($chat_id);
                sendMessage($chat_id, $responseText, $template_management_keyboard);
                exit();

            case 'waiting_for_template_id_to_delete':
                $responseText = deleteTemplateFromDB($pdo, $text);
                clear_admin_state($chat_id);
                sendMessage($chat_id, $responseText, $template_management_keyboard);
                exit();
        }
    }

    // LOTTERY RESULT PARSING
    $parsedResult = LotteryParser::parse($text, $pdo);
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
    // Define keyboard layouts
    $main_menu_keyboard = json_encode(['keyboard' => [
        [['text' => '👤 用户管理'], ['text' => '📝 模板管理']],
        [['text' => '⚙️ 系统设置']]
    ], 'resize_keyboard' => true]);

    $user_management_keyboard = json_encode(['keyboard' => [
        [['text' => '➕ 添加用户'], ['text' => '➖ 删除用户']],
        [['text' => '📋 列出所有用户']],
        [['text' => '⬅️ 返回主菜单']]
    ], 'resize_keyboard' => true]);

    $template_management_keyboard = json_encode(['keyboard' => [
        [['text' => '➕ 添加新模板'], ['text' => '📋 查看所有模板']],
        [['text' => '🗑️ 删除模板']],
        [['text' => '⬅️ 返回主菜单']]
    ], 'resize_keyboard' => true]);

    $system_settings_keyboard = json_encode(['keyboard' => [
        [['text' => '🔑 设定API密钥'], ['text' => 'ℹ️ 检查密钥状态']],
        [['text' => '⬅️ 返回主菜单']]
    ], 'resize_keyboard' => true]);

    // Command mapping
    $command_map = [
        // Main Menu
        '👤 用户管理' => '/user_management',
        '📝 模板管理' => '/template_management',
        '⚙️ 系统设置' => '/system_settings',
        // User Management
        '➕ 添加用户' => '/adduser',
        '➖ 删除用户' => '/deluser',
        '📋 列出所有用户' => '/listusers',
        // Template Management (placeholders)
        '➕ 添加新模板' => '/add_template',
        '📋 查看所有模板' => '/list_templates',
        '🗑️ 删除模板' => '/delete_template',
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
                $state_payload = json_encode(['state' => 'waiting_for_template_pattern', 'data' => []]);
                set_admin_state($chat_id, $state_payload);
                $responseText = "1/3: 请发送新模板的正则表达式 (PCRE)。\n\n*重要*: 表达式必须有3个捕获组:\n1. (`lottery_name`)\n2. (`issue_number`)\n3. (`numbers_string`)\n\n例如: `/(香港六合彩)第(\d+)期开奖号码: ([\d\s,]+)/u`\n\n发送 `/start` 可随时取消。";
                break;
            case '/list_templates':
                $responseText = listTemplatesFromDB($pdo);
                break;
            case '/delete_template':
                set_admin_state($chat_id, json_encode(['state' => 'waiting_for_template_id_to_delete']));
                $responseText = "请输入您想删除的模板的数字ID。\n\n发送 `/start` 可随时取消。";
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
