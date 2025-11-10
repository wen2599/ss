<?php
// File: backend/setup_bot.php (Final version that reads from .env)

// --- 独立的 .env 加载器 (和 receiver.php 中那个一样) ---
function load_env_for_setup() {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        die("Error: .env file not found in " . __DIR__);
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        die("Error: Could not read .env file.");
    }
    foreach ($lines as $line) {
        if (strpos(trim($line), ';') === 0) continue;
        if (strpos($line, ';') !== false) $line = substr($line, 0, strpos($line, ';'));
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            // 这里我们只需要 getenv 能工作就行
            putenv(trim($name) . "=" . trim(trim($value), "\"'"));
        }
    }
}

// 加载配置
load_env_for_setup();

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$webhook_secret = getenv('TELEGRAM_WEBHOOK_SECRET'); // 从 .env 读取新密钥

if (!$bot_token || !$webhook_secret) {
    die("Error: TELEGRAM_BOT_TOKEN or TELEGRAM_WEBHOOK_SECRET not found in .env file.");
}

// 直接指向我们轻量级的 receiver.php
$webhook_url = "https://wenge.cloudns.ch/telegram/receiver.php?secret=" . urlencode($webhook_secret);

$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($webhook_url);

// 调用 API 并显示结果
$response = file_get_contents($api_url);
header('Content-Type: application/json');
echo $response;
?>