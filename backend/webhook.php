<?php
// webhook.php

// 开启日志记录，但不直接显示错误给 Telegram
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 所有文件都在同级，直接 require
require_once 'database.php';

// --- 安全性检查 ---
$secret_token_header = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
// 使用新的辅助函数来获取 Secret Token
$expected_secret = get_env_variable('TELEGRAM_WEBHOOK_SECRET');

if (empty($expected_secret) || $secret_token_header !== $expected_secret) {
    // 增加日志，帮助我们判断是哪个环节出了问题
    if (empty($expected_secret)) {
        error_log("Webhook Forbidden: TELEGRAM_WEBHOOK_SECRET is empty in .env or failed to load.");
    } else {
        error_log("Webhook Forbidden: Secret token mismatch. Header: [".$secret_token_header."], Expected: [".$expected_secret."]");
    }
    http_response_code(403);
    exit('Forbidden');
}

// --- 获取并解码输入 ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    http_response_code(400);
    exit('Bad Request');
}

function parseLotteryNumber($text) {
    $lines = explode("\n", $text);
    $found_result_line = false;
    foreach ($lines as $line) {
        $trimmed_line = trim($line);
        if ($found_result_line) {
            if (preg_match('/^(\d{2}\s+)+\d{2}$/', $trimmed_line)) {
                return $trimmed_line;
            }
        }
        if (strpos($trimmed_line, '开奖结果:') !== false) {
            $found_result_line = true;
        }
    }
    return null;
}

// --- 逻辑处理 ---
if (isset($update['channel_post']['text'])) {
    $message_text = $update['channel_post']['text'];
    $lottery_number_string = parseLotteryNumber($message_text);
    
    if ($lottery_number_string !== null) {
        if (!Database::saveLotteryNumber($lottery_number_string)) {
            error_log("Database Error: Failed to save parsed lottery number: '" . $lottery_number_string . "'");
        }
    } else {
        error_log("Parsing Error: Could not parse lottery number from message.");
    }
}

http_response_code(200);
echo "OK";
?>