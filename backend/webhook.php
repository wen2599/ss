<?php
// webhook.php - 接收 Telegram 更新

require_once 'db.php';

// 加载 .env
$env = parse_ini_file(__DIR__ . '/.env');

// --- 安全性检查 ---
$request_uri = $_SERVER['REQUEST_URI'];
$secret_path = $env['WEBHOOK_SECRET_PATH'];
if (strpos($request_uri, $secret_path) === false) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// --- 获取并处理数据 ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json);

error_log("Webhook received: " . $update_json);

if (!$update) {
    http_response_code(400);
    exit;
}

if (isset($update->channel_post) && $update->channel_post->chat->id == $env['TELEGRAM_CHANNEL_ID']) {
    $message_text = trim($update->channel_post->text);
    
    // 正则表达式，用于匹配三种模板
    // (.+?)              - Group 1: 捕获彩票类型 (e.g., 新澳门六合彩)
    // 第:(\d+)\s*期        - Group 2: 捕获期号 (e.g., 2025301), 允许期号和“期”之间有空格
    // 开奖结果:\s*\n\s*    - 匹配 "开奖结果:"，换行符，和可选的空格
    // ([\d\s]+)          - Group 3: 捕获数字行 (e.g., 47  10  09...)
    $pattern = '/^(.+?)第:(\d+)\s*期开奖结果:\s*\n\s*([\d\s]+)/m';

    // 为了让 . 匹配换行符之外的所有字符，我们不能用 s 修饰符
    // 我们将消息按行分割处理，这样更稳定
    $lines = explode("\n", $message_text);
    
    $lottery_type = null;
    $issue_number = null;
    $winning_numbers = null;

    if (count($lines) >= 3) {
        // 第2行应包含类型和期号
        if (preg_match('/^(.*?)第:(\d+)\s*期开奖结果:$/', trim($lines[1]), $matches)) {
            $lottery_type = trim($matches[1]);
            $issue_number = trim($matches[2]);
            
            // 第3行应为开奖号码
            $potential_numbers = trim($lines[2]);
            if (preg_match('/^[\d\s]+$/', $potential_numbers)) {
                 // 清理多余的空格，统一用单个空格分隔
                $winning_numbers = preg_replace('/\s+/', ' ', $potential_numbers);
            }
        }
    }

    if ($lottery_type && $issue_number && $winning_numbers) {
        $conn = get_db_connection();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $lottery_type, $issue_number, $winning_numbers);
                if ($stmt->execute()) {
                    error_log("Successfully inserted: [$lottery_type] #$issue_number - $winning_numbers");
                    http_response_code(200);
                    echo "OK";
                } else {
                    error_log("Failed to execute statement: " . $stmt->error);
                    http_response_code(500);
                }
                $stmt->close();
            } else {
                error_log("Failed to prepare statement: " . $conn->error);
                http_response_code(500);
            }
        } else {
            error_log("Failed to get DB connection.");
            http_response_code(500);
        }
    } else {
        error_log("Failed to parse message: " . $message_text);
        http_response_code(200); // 即使解析失败也返回200，避免Telegram重试
    }
} else {
    error_log("Update is not from the target channel or is not a channel post.");
    http_response_code(200);
}
