<?php
// backend/webhook.php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/env_utils.php';
require_once __DIR__ . '/lib/telegram_utils.php';

// --- Get Configuration ---
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$admin_id = getenv('TELEGRAM_ADMIN_ID');

// --- Security Checks ---
if (!$bot_token || !$admin_id) {
    http_response_code(500);
    error_log("Bot Token or Admin ID is not configured.");
    exit("Configuration error.");
}

// --- Get Incoming Update ---
$update = json_decode(file_get_contents('php://input'), true);

if (!isset($update['message']['text']) || !isset($update['message']['from']['id'])) {
    http_response_code(200); // Respond OK to Telegram, but do nothing
    exit;
}

$chat_id = $update['message']['from']['id'];
$text = $update['message']['text'];

// --- Admin-Only Authorization ---
if ((string)$chat_id !== (string)$admin_id) {
    send_telegram_message($bot_token, $chat_id, "Sorry, you are not authorized to use this command.");
    http_response_code(403);
    exit("Unauthorized.");
}

// --- Command Handling ---
if (preg_match('/^\/set_deepseek_key (\S+)$/', $text, $matches)) {
    $new_key = $matches[1];

    if (update_env_file('DEEPSEEK_API_KEY', $new_key)) {
        // Success
        $feedback = "✅ DeepSeek API Key has been successfully updated!";
        send_telegram_message($bot_token, $admin_id, $feedback);
        http_response_code(200);
        echo $feedback;
    } else {
        // Failure
        $feedback = "❌ Failed to update the DeepSeek API Key. Please check server logs.";
        send_telegram_message($bot_token, $admin_id, $feedback);
        http_response_code(500);
        echo $feedback;
    }
} else {
    // Command not recognized
    $feedback = "Sorry, I didn't understand that command. Please use the format: `/set_deepseek_key YOUR_KEY`";
    send_telegram_message($bot_token, $admin_id, $feedback);
    http_response_code(200);
    echo $feedback;
}
