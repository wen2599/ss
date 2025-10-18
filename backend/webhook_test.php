<?php
// webhook_test.php

// 文件路径
$logFile = __DIR__ . '/webhook_test.log';

// 清空旧日志，以便我们只看最新的测试结果
file_put_contents($logFile, "");

function log_message($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

log_message("--- Webhook Test Script Started ---");

// --- 1. 获取 .env 中的 Secret Token ---
$secret_from_env = null;
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), 'TELEGRAM_WEBHOOK_SECRET=') === 0) {
            $secret_from_env = substr(trim($line), strlen('TELEGRAM_WEBHOOK_SECRET='));
            // 移除可能存在的引号
            $secret_from_env = trim($secret_from_env, "\"'");
            break;
        }
    }
}

if ($secret_from_env) {
    log_message("Successfully loaded TELEGRAM_WEBHOOK_SECRET from .env file.");
} else {
    log_message("CRITICAL: Failed to load TELEGRAM_WEBHOOK_SECRET from .env file!");
    http_response_code(500);
    exit("Internal Server Error: Secret not configured.");
}

// --- 2. 获取 Telegram 发送过来的 Secret Token ---
$secret_from_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '[Not Set]';
log_message("Received Secret Token from header: " . $secret_from_header);

// --- 3. 验证 Token ---
log_message("Comparing received token with the one from .env...");
if (hash_equals($secret_from_env, $secret_from_header)) {
    log_message("SUCCESS: Secret tokens match!");
} else {
    log_message("FAILURE: Secret tokens DO NOT MATCH!");
    log_message("   - From .env:     " . $secret_from_env);
    log_message("   - From Header:   " . $secret_from_header);
    
    // 即使验证失败，我们也不返回 403，而是返回 200 OK
    // 这样做的目的是为了不让 Telegram 因为收到 403 而停止发送请求
    // 我们可以通过日志来判断问题
    http_response_code(200); 
    echo json_encode(['status' => 'error', 'reason' => 'token mismatch']);
    exit();
}

// --- 4. 如果 Token 验证通过，记录收到的所有数据 ---
log_message("\n--- PAYLOAD DATA ---");
$body = file_get_contents('php://input');
log_message("Request Body:\n" . $body);

log_message("\n--- SERVER & HEADERS ---");
log_message(print_r($_SERVER, true));

log_message("\n--- Webhook Test Script Finished Successfully ---");

// 告诉 Telegram 我们成功处理了请求
http_response_code(200);
echo json_encode(['status' => 'ok']);

?>
