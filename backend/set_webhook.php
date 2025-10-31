<?php
// backend/set_webhook.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/env_loader.php';

$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$backend_url = $_ENV['BACKEND_URL'] ?? null;

if (!$bot_token || !$backend_url) {
    die("Error: TELEGRAM_BOT_TOKEN and BACKEND_URL must be set in your .env file.");
}

// Ensure the URL has a trailing slash
if (substr($backend_url, -1) !== '/') {
    $backend_url .= '/';
}

$webhook_url = $backend_url . 'bot/webhook.php';

$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($webhook_url);

$response = @file_get_contents($api_url);

if ($response === false) {
    echo "Error: Could not connect to the Telegram API.";
} else {
    $decoded_response = json_decode($response, true);
    if ($decoded_response && $decoded_response['ok']) {
        echo "Webhook set successfully to: {$webhook_url}\n";
        echo "Response: " . $response;
    } else {
        echo "Error setting webhook:\n";
        echo $response;
    }
}
