<?php
// --- PING DIAGNOSTIC SCRIPT ---
// This script has ZERO dependencies. Its only job is to respond to the admin.

// --- Step 1: Manually load ONLY the variables we need ---
$adminId = null;
$botToken = null;
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
        foreach ($lines as $line) {
            if (strpos($line, 'TELEGRAM_ADMIN_ID') !== false) {
                list(, $adminId) = explode('=', $line, 2);
                $adminId = trim(trim($adminId), "'\"");
            }
            if (strpos($line, 'TELEGRAM_BOT_TOKEN') !== false) {
                list(, $botToken) = explode('=', $line, 2);
                $botToken = trim(trim($botToken), "'\"");
            }
        }
    }
}

// --- Step 2: Define a minimal send message function ---
function send_ping_message($chatId, $text, $token) {
    if (!$chatId || !$text || !$token) return;
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = ['chat_id' => $chatId, 'text' => $text];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    @curl_exec($ch);
    curl_close($ch);
}

// --- Step 3: Process the update ---
$update = json_decode(file_get_contents('php://input'), true);

if ($update && $adminId) {
    // Get the user ID from any possible update type
    $userId = $update['message']['from']['id']
           ?? $update['callback_query']['from']['id']
           ?? null;

    // If the message is from the admin, send a pong.
    if ($userId && (string)$userId === (string)$adminId) {
        send_ping_message($adminId, "PONG! The webhook is receiving messages.", $botToken);
    }
}

// --- Step 4: Always acknowledge the request to Telegram ---
http_response_code(200);
exit();
?>