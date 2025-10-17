<?php
/**
 * telegramWebhook.php (ECHO DIAGNOSTIC)
 *
 * This is a temporary script to diagnose parsing issues.
 * It will "echo" the raw text of any channel post it receives directly
 * to the admin's Telegram chat.
 */

// --- Environment & Logging Setup ---
define('BASE_DIR', __DIR__);
if (file_exists(BASE_DIR . '/.env')) {
    $lines = file(BASE_DIR . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . "=" . trim($value, "'\""));
    }
}

// --- Webhook Security Validation ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!$expectedSecret || !hash_equals($expectedSecret, $receivedHeader)) {
    http_response_code(403);
    exit('Forbidden');
}

// --- Load ONLY telegram_helpers ---
// We don't need the other dependencies for this diagnostic script.
require_once BASE_DIR . '/telegram_helpers.php';

// --- Main Update Processing ---
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    exit();
}

// --- Echo Logic ---
if (isset($update['channel_post'])) {
    $adminId = getenv('TELEGRAM_ADMIN_ID');
    $post = $update['channel_post'];
    $chatId = $post['chat']['id'] ?? null;
    $text = $post['text'] ?? '';
    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');

    // If it's from the correct channel, echo the text to the admin
    if ($adminId && !empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        $message_to_admin = "--- RECEIVED FROM CHANNEL ---\n\n" . $text;
        sendTelegramMessage($adminId, $message_to_admin);
    }
}

// Acknowledge all updates to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>