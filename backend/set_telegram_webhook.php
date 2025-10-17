<?php

// This script sets the Telegram Webhook URL.

require_once __DIR__ . '/config.php'; // Load config for env variables and sendTelegramRequest
require_once __DIR__ . '/telegram_helpers.php'; // Ensure sendTelegramRequest is available

header('Content-Type: application/json');

$botToken = getenv('TELEGRAM_BOT_TOKEN');
$backendPublicUrl = getenv('BACKEND_PUBLIC_URL');
$webhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET');

// Validate environment variables
if (empty($botToken) || $botToken === 'your_telegram_bot_token_here') {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'TELEGRAM_BOT_TOKEN is not configured.']);
    exit();
}
if (empty($backendPublicUrl)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'BACKEND_PUBLIC_URL is not configured.']);
    exit();
}
if (empty($webhookSecret)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'TELEGRAM_WEBHOOK_SECRET is not configured.']);
    exit();
}

// Construct the correct Webhook URL
$webhookUrl = rtrim($backendPublicUrl, '/') . '/index.php?endpoint=telegramWebhook';

// Data for setWebhook API call
$data = [
    'url' => $webhookUrl,
    'secret_token' => $webhookSecret,
    'max_connections' => 40, // Recommended value
];

// Check if we need to drop pending updates
if (isset($_GET['drop_pending_updates']) && $_GET['drop_pending_updates'] === 'true') {
    $data['drop_pending_updates'] = true;
}

// Send the setWebhook request to Telegram API
$response = sendTelegramRequest('setWebhook', $data);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send setWebhook request.']);
    exit();
}

$decodedResponse = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to decode Telegram API response.', 'raw_response' => $response]);
    exit();
}

if (!isset($decodedResponse['ok']) || $decodedResponse['ok'] !== true) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Telegram API error setting webhook.', 'details' => $decodedResponse]);
    exit();
}

$message = 'Telegram Webhook set successfully!';
if (isset($data['drop_pending_updates']) && $data['drop_pending_updates']) {
    $message .= ' Pending updates have been dropped.';
}

echo json_encode(['status' => 'success', 'message' => $message, 'webhook_url' => $webhookUrl]);

?>