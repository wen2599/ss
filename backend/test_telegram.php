<?php
// public/test_telegram.php

// Correctly require the config file from the same directory
require_once __DIR__ . '/config.php';

// Get the admin chat ID and bot token from environment variables
$adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID');
$botToken = getenv('TELEGRAM_BOT_TOKEN');

if (!$adminChatId || !$botToken) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error: Environment variables for Telegram are not set.']);
    exit;
}

$message = "This is a test message from the server to confirm the Telegram API connection.";

// Call the sendTelegramMessage function
$result = sendTelegramMessage($adminChatId, $message);

// Output the result
header('Content-Type: application/json');
echo $result;
