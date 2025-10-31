<?php
// backend/set_webhook.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/env_loader.php';

$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$app_url = $_ENV['APP_URL'] ?? null;

if (!$bot_token || !$app_url) {
    die("Error: TELEGRAM_BOT_TOKEN and APP_URL must be set in your .env file.");
}

// Ensure the URL has a trailing slash
if (substr($app_url, -1) !== '/') {
    $app_url .= '/';
}

$webhook_url = $app_url . 'backend/bot/webhook.php';

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
