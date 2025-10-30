<?php
/**
 * 文件名: telegram_webhook.php
 * 路径: backend/ (项目根目录)
 * 描述: 接收并处理来自 Telegram Bot 的所有更新。
 */
ini_set('display_errors', 0); // Webhook 不应向 Telegram 显示错误
error_reporting(E_ALL);
// 将错误记录到服务器日志文件，以便我们调试
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/telegram_errors.log');

// 引入核心文件
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/helpers.php'; // 包含 is_admin()

// --- 1. 安全性验证 ---
// 验证请求是否真的来自 Telegram，通过我们设置的 Secret Token
$secret_token_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (hash_equals(TELEGRAM_WEBHOOK_SECRET, $secret_token_header) === false) {
    http_response_code(403);
    error_log('Invalid Secret Token. Access denied.');
    exit('Forbidden');
}

// --- 2. 获取并解析输入 ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) {
    // 如果没有接收到有效的数据，静默退出
    exit();
}

// 从更新中提取关键信息
$message = $update['message'] ?? null;
$chat_id = $message['chat']['id'] ?? null;
$user_id = $message['from']['id'] ?? null;
$text = trim($message['text'] ?? '');

// 如果没有消息或发送者，则忽略
if (!$chat_id || !$user_id) {
    exit();
}

// --- 3. 管理员身份验证 ---
// 只处理来自预设管理员ID的消息
if (!is_admin($user_id)) {
    error_log("Unauthorized message from user ID: {$user_id}, Chat ID: {$chat_id}");
    send_telegram_message($chat_id, "抱歉，您无权使用此机器人。");
    exit();
}

// --- 4. 命令路由 ---
$parts = explode(' ', $text);
$command = strtolower($parts[0] ?? '');

try {
    switch ($command) {
        case '/start':
        case '/help':
            $help_text = "您好，管理员！可用命令如下：\n\n";
            $help_text .= "/add <期号> <平码> <特码>\n";
            $help_text .= "示例: `/add 20240525 01,02,03,04,05,06 07`\n\n";
            $help_text .= "/latest - 查询最新一期记录\n";
            $help_text .= "/delete <期号> - 删除指定期号记录\n\n";
            $help_text .= "/set_gemini_key <API密钥> - 设置Gemini API密钥";
            send_telegram_message($chat_id, $help_text);
            break;

        case '/add':
            handle_add_lottery($chat_id, $parts);
            break;

        case '/latest':
            handle_get_latest($chat_id);
            break;

        case '/delete':
            handle_delete_lottery($chat_id, $parts);
            break;
            
        case '/set_gemini_key':
            handle_set_gemini_key($chat_id, $parts);
            break;

        default:
            send_telegram_message($chat_id, "🤔 未知命令。发送 /help 查看可用命令。");
            break;
    }
} catch (Exception $e) {
    // 捕获所有未预料的错误
    error_log("Telegram Bot Unhandled Exception: " . $e->getMessage());
    send_telegram_message($chat_id, "❌ 处理命令时发生了一个内部错误。");
}

// --- 5. 命令处理器函数 ---

function handle_add_lottery($chat_id, $parts) {
    if (count($parts) !== 4) {
        send_telegram_message($chat_id, "格式错误！\n正确格式: `/add <期号> <平码> <特码>`\n例如: `/add 20240525 01,02,03,04,05,06 07`");
        return;
    }
    
    $issue_number = $parts[1];
    $winning_numbers = $parts[2];
    $special_number = $parts[3];
    
    // 基本验证
    $normal_nums = explode(',', $winning_numbers);
    if (count($normal_nums) !== 6) {
        send_telegram_message($chat_id, "❌ 平码必须是6个号码，用逗号分隔。");
        return;
    }
    // 尝试从期号解析日期，如果失败则使用当天日期
    $draw_date_obj = DateTime::createFromFormat('Ymd', substr($issue_number, 0, 8));
    $draw_date = $draw_date_obj ? $draw_date_obj->format('Y-m-d') : date('Y-m-d');

    try {
        $db = get_db_connection();
        // 使用 ON DUPLICATE KEY UPDATE 来支持修改
        $stmt = $db->prepare(
            "INSERT INTO lottery_results (issue_number, winning_numbers, special_number, draw_date) 
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE winning_numbers = ?, special_number = ?, draw_date = ?"
        );
        $stmt->execute([
            $issue_number, $winning_numbers, $special_number, $draw_date,
            $winning_numbers, $special_number, $draw_date // for update
        ]);
        
        send_telegram_message($chat_id, "✅ 期号 `{$issue_number}` 的开奖结果已成功保存/更新。");

    } catch (PDOException $e) {
        error_log("DB Error in /add command: " . $e->getMessage());
        send_telegram_message($chat_id, "❌ 数据库操作失败，请检查期号是否重复或格式有误。");
    }
}

function handle_get_latest($chat_id) {
    try {
        $db = get_db_connection();
        $stmt = $db->query("SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $response = "最新一期记录：\n";
            $response .= "期号: `{$result['issue_number']}`\n";
            $response .= "平码: `{$result['winning_numbers']}`\n";
            $response .= "特码: `{$result['special_number']}`\n";
            $response .= "日期: `{$result['draw_date']}`";
            send_telegram_message($chat_id, $response, 'MarkdownV2');
        } else {
            send_telegram_message($chat_id, "数据库中暂无记录。");
        }
    } catch (PDOException $e) {
        error_log("DB Error in /latest command: " . $e->getMessage());
        send_telegram_message($chat_id, "❌ 查询最新记录时数据库出错。");
    }
}

function handle_delete_lottery($chat_id, $parts) {
    if (count($parts) !== 2) {
        send_telegram_message($chat_id, "格式错误！\n正确格式: `/delete <期号>`");
        return;
    }
    $issue_number = $parts[1];

    try {
        $db = get_db_connection();
        $stmt = $db->prepare("DELETE FROM lottery_results WHERE issue_number = ?");
        $stmt->execute([$issue_number]);
        
        if ($stmt->rowCount() > 0) {
            send_telegram_message($chat_id, "🗑️ 期号 `{$issue_number}` 的记录已成功删除。");
        } else {
            send_telegram_message($chat_id, "🤷 未找到期号为 `{$issue_number}` 的记录。");
        }
    } catch (PDOException $e) {
        error_log("DB Error in /delete command: " . $e->getMessage());
        send_telegram_message($chat_id, "❌ 删除记录时数据库出错。");
    }
}

function handle_set_gemini_key($chat_id, $parts) {
    if (count($parts) !== 2 || strlen($parts[1]) < 10) {
        send_telegram_message($chat_id, "格式错误！\n正确格式: `/set_gemini_key <API密钥>`");
        return;
    }
    $api_key = $parts[1];

    try {
        $db = get_db_connection();
        // 使用 INSERT ... ON DUPLICATE KEY UPDATE 来插入或更新
        $stmt = $db->prepare(
            "INSERT INTO settings (key_name, key_value) 
             VALUES ('gemini_api_key', ?)
             ON DUPLICATE KEY UPDATE key_value = ?"
        );
        $stmt->execute([$api_key, $api_key]);

        send_telegram_message($chat_id, "🔑 Gemini API 密钥已成功更新。");

    } catch (PDOException $e) {
        error_log("DB Error in /set_gemini_key command: " . $e->getMessage());
        send_telegram_message($chat_id, "❌ 更新密钥时数据库出错。");
    }
}

/**
 * 向 Telegram API 发送消息
 * @param int $chat_id
 * @param string $text
 * @param string|null $parse_mode (e.g., 'MarkdownV2', 'HTML')
 */
function send_telegram_message($chat_id, $text, $parse_mode = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];
    if ($parse_mode) {
        $payload['parse_mode'] = $parse_mode;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    // 增加超时以避免PHP进程挂起
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    // 检查是否有curl错误
    if(curl_errno($ch)){
        error_log('cURL error sending Telegram message: ' . curl_error($ch));
    }
    curl_close($ch);
}
?>