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
    error_log("CRITICAL: TELEGRAM_BOT_TOKEN is not set or is a placeholder.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'TELEGRAM_BOT_TOKEN is not configured.']);
    exit();
}

if (empty($backendPublicUrl)) {
    error_log("CRITICAL: BACKEND_PUBLIC_URL is not set.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'BACKEND_PUBLIC_URL is not configured.']);
    exit();
}

if (empty($webhookSecret)) {
    error_log("CRITICAL: TELEGRAM_WEBHOOK_SECRET is not set.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'TELEGRAM_WEBHOOK_SECRET is not configured.']);
    exit();
}

// Construct the correct Webhook URL
// Assuming backend/ is the root for the public URL, so no /backend/ is needed.
$webhookUrl = rtrim($backendPublicUrl, '/') . '/index.php?endpoint=telegramWebhook';

// Data for setWebhook API call
$data = [
    'url' => $webhookUrl,
    'secret_token' => $webhookSecret,
    'max_connections' => 40, // Recommended value
];

// Send the setWebhook request to Telegram API
$response = sendTelegramRequest('setWebhook', $data);

if ($response === false) {
    error_log("Failed to send setWebhook request via sendTelegramRequest.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send setWebhook request.']);
    exit();
}

$decodedResponse = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Failed to decode Telegram setWebhook response: " . json_last_error_msg() . ". Raw response: " . $response);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to decode Telegram API response.']);
    exit();
}

if (!isset($decodedResponse['ok']) || $decodedResponse['ok'] !== true) {
    error_log("Telegram setWebhook API returned an error: " . json_encode($decodedResponse));
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Telegram API error setting webhook.', 'details' => $decodedResponse]);
    exit();
}

error_log("Telegram Webhook set successfully! URL: " . $webhookUrl);
echo json_encode(['status' => 'success', 'message' => 'Telegram Webhook set successfully!', 'webhook_url' => $webhookUrl]);

?>