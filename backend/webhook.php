<?php
// backend/webhook.php

require_once 'database.php';

// --- 安全性检查 ---
// 1. 验证 Webhook Secret Token
$secret_token_header = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
$expected_secret = getenv('TELEGRAM_WEBHOOK_SECRET');

if (!$expected_secret || $secret_token_header !== $expected_secret) {
    // 如果令牌不匹配，立即停止执行并返回 403 Forbidden
    http_response_code(403);
    error_log('Webhook secret token mismatch.');
    exit('Forbidden');
}

// 2. 获取并解码输入
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    // 如果没有收到有效的 JSON 数据，则退出
    http_response_code(400);
    exit('Bad Request');
}

// --- 逻辑处理 ---
// 我们只关心频道里的新帖子 (channel_post)
if (isset($update['channel_post']['text'])) {
    $message_text = trim($update['channel_post']['text']);
    
    // 假设开奖号码格式为 "开奖号码: 123456" 或纯数字
    // 您可以根据实际情况修改这里的解析逻辑
    $lottery_number = '';

    // 尝试用冒号分割
    $parts = explode(':', $message_text);
    if (count($parts) > 1) {
        $lottery_number = trim(end($parts));
    } elseif (is_numeric($message_text)) {
        // 如果整条消息就是数字
        $lottery_number = $message_text;
    }

    // 如果成功解析出号码，则保存到数据库
    if (!empty($lottery_number)) {
        $success = Database::saveLotteryNumber($lottery_number);
        if (!$success) {
            // 如果保存失败，记录日志
            error_log("Failed to save lottery number: " . $lottery_number);
        }
    } else {
        error_log("Could not parse lottery number from message: " . $message_text);
    }
}

// 告诉 Telegram 我们已成功处理更新，防止重试
http_response_code(200);
echo "OK";
?>