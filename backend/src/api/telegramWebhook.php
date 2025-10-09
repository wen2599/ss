<?php

// API handler for incoming Telegram Bot Webhooks

require_once __DIR__ . '/../core/Response.php';

// --- Configuration & Security ---

// These are now loaded from .env via config.php
$secretToken = TELEGRAM_WEBHOOK_SECRET;
$allowedChannelId = TELEGRAM_CHANNEL_ID;

// The header that contains the secret token
$secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

// Path to the file where the latest lottery number will be stored
$storagePath = __DIR__ . '/../../data/lottery_latest.json';

// --- Validation ---

// 1. Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['error' => 'Method Not Allowed'], 405);
    exit;
}

// 2. Validate the secret token to ensure the request is from Telegram
if (!$secretToken || $secretToken !== $secretHeader) { // Check if token is set
    error_log('Invalid webhook secret token attempt.');
    Response::json(['error' => 'Unauthorized'], 401);
    exit;
}

// --- Process the Request ---

$update = $GLOBALS['requestBody'] ?? null;

if (!$update) {
    Response::json(['error' => 'No data received'], 400);
    exit;
}

// --- Extract and Validate the Message ---

if (isset($update['channel_post'])) {
    $channelPost = $update['channel_post'];
    $channelId = $channelPost['chat']['id'] ?? null;
    $messageText = trim($channelPost['text'] ?? '');

    // 3. Validate Channel ID
    if (!$allowedChannelId || $channelId != $allowedChannelId) {
        error_log("Message from unauthorized channel ID: {$channelId}. Allowed: {$allowedChannelId}");
        Response::json(['error' => 'Forbidden: Message from wrong channel'], 403);
        exit;
    }

    if ($messageText) {
        $receivedAt = date('Y-m-d H:i:s T');

        $dataToStore = [
            'lottery_number' => $messageText,
            'received_at_utc' => $receivedAt
        ];

        if (file_put_contents($storagePath, json_encode($dataToStore), LOCK_EX)) {
            Response::json(['status' => 'success', 'message' => 'Data stored']);
        } else {
            error_log('Failed to write to storage file: ' . $storagePath);
            Response::json(['error' => 'Internal Server Error'], 500);
        }
    } else {
        Response::json(['status' => 'ok', 'message' => 'Empty message, ignored']);
    }

} else {
    Response::json(['status' => 'ok', 'message' => 'Payload not a channel post, ignored']);
}
