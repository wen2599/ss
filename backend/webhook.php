<?php
// webhook.php - 接收 Telegram 更新

require_once 'db.php';

// 加载 .env
$env = parse_ini_file(__DIR__ . '/.env');

// --- 安全性检查 ---
// 验证请求路径是否包含秘密字符串
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

// 记录原始请求用于调试
error_log("Webhook received: " . $update_json);

if (!$update) {
    http_response_code(400);
    echo "Bad Request";
    exit;
}

// 检查是否是来自指定频道的帖子
if (isset($update->channel_post) && $update->channel_post->chat->id == $env['TELEGRAM_CHANNEL_ID']) {
    $message_text = $update->channel_post->text;
    
    // 从消息中提取号码 (假设号码是消息中的数字)
    // 这个正则表达式会提取消息中所有连续的数字
    if (preg_match('/(\d+)/', $message_text, $matches)) {
        $lottery_number = $matches[1];

        // 连接数据库
        $conn = get_db_connection();
        if ($conn) {
            // 使用预处理语句防止 SQL 注入
            $stmt = $conn->prepare("INSERT INTO lottery_numbers (number) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param("s", $lottery_number);
                if ($stmt->execute()) {
                    error_log("Successfully inserted number: " . $lottery_number);
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
            // 不需要在这里关闭连接，因为 get_db_connection 使用了静态连接
        } else {
            error_log("Failed to get DB connection.");
            http_response_code(500);
        }
    } else {
        error_log("No number found in message: " . $message_text);
        http_response_code(200); // 即使没找到号码也返回200，避免Telegram重试
    }
} else {
    error_log("Update is not from the target channel or is not a channel post.");
    http_response_code(200);
}