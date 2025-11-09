<?php
// File: backend/setup_bot.php (Final version, points to Cloudflare Worker)

// 加载配置以获取 token
require_once __DIR__ . '/config.php';

$bot_token = config('TELEGRAM_BOT_TOKEN');

// 【关键】将此 URL 替换为你自己的 Cloudflare Worker 的 URL
// 它应该看起来像 https://<你的Worker名字>.<你的Cloudflare子域>.workers.dev
$worker_url = "https://ssgamil.wenge666.workers.dev"; // <-- ！！！请务必替换这里！！！

// 构建 Telegram API URL for setWebhook
$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($worker_url);

// 调用 API 并显示结果
try {
    $response = file_get_contents($api_url);
    if ($response === false) {
        throw new Exception("file_get_contents failed to fetch the Telegram API URL.");
    }
    header('Content-Type: application/json');
    echo $response;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to set webhook', 'details' => $e->getMessage()]);
}
?>