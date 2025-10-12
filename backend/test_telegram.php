<?php
// test_telegram.php

require_once __DIR__ . '/config.php';

// Get the admin chat ID and bot token from environment variables
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
$botToken = getenv('TELEGRAM_BOT_TOKEN');

if (!$adminChatId || !$botToken) {
    echo "Error: Environment variables for Telegram are not set.";
    exit;
}

$message = "This is a test message from the server to confirm the Telegram API connection.";

// Call the sendTelegramMessage function
$result = sendTelegramMessage($adminChatId, $message);

// Output the result
header('Content-Type: application/json');
echo $result;
