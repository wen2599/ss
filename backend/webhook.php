<?php
// webhook.php - 接收 Telegram 更新 (使用 GET 参数进行验证)

// 加载核心依赖
require_once 'db.php';

// 加载 .env 配置文件
// __DIR__ 确保路径是相对于当前文件的，非常可靠
$env_path = __DIR__ . '/.env';
$env = parse_ini_file($env_path);
if ($env === false) {
    http_response_code(500);
    error_log("FATAL: Could not read .env file at {$env_path}");
    echo "Server configuration error.";
    exit;
}

// =================================================================
// 1. 安全性检查 (核心修改在这里)
// =================================================================
// 我们从 .env 文件中读取预设的 secret
$expected_secret = $env['WEBHOOK_SECRET_PATH']; // 变量名保持不变，但我们知道它现在用作查询参数

// 我们从 URL 的 GET 查询参数中获取传入的 secret
$received_secret = isset($_GET['secret']) ? $_GET['secret'] : '';

// 严格比较两个 secret 是否一致
if ($received_secret !== $expected_secret) {
    http_response_code(403);
    error_log("Forbidden access to webhook: Incorrect or missing secret. Expected '{$expected_secret}', but received '{$received_secret}'");
    echo "Forbidden";
    exit;
}

// =================================================================
// 2. 获取并处理 Telegram 数据
// =================================================================
$update_json = file_get_contents('php://input');
$update = json_decode($update_json);

// 记录原始请求用于调试
// error_log("Webhook received: " . $update_json);

if (!$update) {
    http_response_code(400); // Bad Request
    error_log("Webhook Error: Invalid JSON or empty payload.");
    exit;
}

// 检查是否是来自我们指定频道的帖子
if (isset($update->channel_post) && $update->channel_post->chat->id == $env['TELEGRAM_CHANNEL_ID']) {
    $message_text = trim($update->channel_post->text);
    
    // 初始化变量
    $lottery_type = null;
    $issue_number = null;
    $winning_numbers = null;
    
    // 将消息按行分割
    $lines = explode("\n", $message_text);
    
    // 确保消息至少有3行
    if (count($lines) >= 3) {
        // 从第二行解析类型和期号
        if (preg_match('/^(.*?)第:(\d+)\s*期开奖结果:$/', trim($lines[1]), $matches)) {
            $lottery_type = trim($matches[1]);
            $issue_number = trim($matches[2]);
            
            // 从第三行提取号码
            $potential_numbers = trim($lines[2]);
            // 验证第三行是否只包含数字和空格
            if (preg_match('/^[\d\s]+$/', $potential_numbers)) {
                // 清理多余的空格，统一用单个空格分隔
                $winning_numbers = preg_replace('/\s+/', ' ', $potential_numbers);
            }
        }
    }

    // 如果所有信息都成功解析
    if ($lottery_type && $issue_number && $winning_numbers) {
        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $lottery_type, $issue_number, $winning_numbers);
                if ($stmt->execute()) {
                    error_log("OK: Inserted [$lottery_type] #$issue_number - $winning_numbers");
                    http_response_code(200);
                    echo "OK";
                } else {
                    error_log("DB Error: Failed to execute statement: " . $stmt->error);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                error_log("DB Error: Failed to prepare statement: " . $conn->error);
                http_response_code(500);
            }
        } else {
            error_log("DB Error: Failed to get database connection.");
            http_response_code(500);
        }
    } else {
        error_log("Parse Error: Failed to parse message text: " . $message_text);
        // 即使解析失败也返回200，避免Telegram因错误而反复重试
        http_response_code(200); 
    }
} else {
    // 如果不是我们想要的频道或消息类型，也返回200，静默忽略
    // error_log("Info: Ignored an update that was not a target channel post.");
    http_response_code(200);
}