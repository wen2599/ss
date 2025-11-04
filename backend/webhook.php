<?php
// webhook.php - 接收 Telegram 更新 (最终修正版)

require_once 'db.php';

$env_path = __DIR__ . '/.env';
$env = parse_ini_file($env_path);
if ($env === false) {
    http_response_code(500);
    error_log("FATAL: Could not read .env file at {$env_path}");
    echo "Server configuration error.";
    exit;
}

// =================================================================
// 1. 安全性检查
// =================================================================
// 从 .env 读取预设的 secret
$expected_secret = $env['TELEGRAM_WEBHOOK_SECRET'];

// 从 URL 的 GET 查询参数中获取传入的 secret
$received_secret = isset($_GET['secret']) ? $_GET['secret'] : '';

// 严格比较
if (empty($expected_secret) || $received_secret !== $expected_secret) {
    http_response_code(403);
    error_log("Forbidden access to webhook: Incorrect or missing secret.");
    echo "Forbidden";
    exit;
}

// =================================================================
// 2. 获取并处理 Telegram 数据
// =================================================================
$update_json = file_get_contents('php://input');
$update = json_decode($update_json);

if (!$update) {
    http_response_code(400);
    exit;
}

if (isset($update->channel_post) && $update->channel_post->chat->id == $env['TELEGRAM_CHANNEL_ID']) {
    $message_text = trim($update->channel_post->text);
    
    $lottery_type = null;
    $issue_number = null;
    $winning_numbers = null;
    
    $lines = explode("\n", $message_text);
    
    if (count($lines) >= 3) {
        if (preg_match('/^(.*?)第:(\d+)\s*期开奖结果:$/', trim($lines[1]), $matches)) {
            $lottery_type = trim($matches[1]);
            $issue_number = trim($matches[2]);
            
            $potential_numbers = trim($lines[2]);
            if (preg_match('/^[\d\s]+$/', $potential_numbers)) {
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
        http_response_code(200); 
    }
} else {
    http_response_code(200);
}