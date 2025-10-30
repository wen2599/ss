<?php
// backend/set_webhook.php
// This script sets the Telegram Bot's webhook.
// Run this file once from your server's command line after deploying the backend.

require_once __DIR__ . '/bootstrap.php';

// This script is intended to be run from the command line.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from the command line for security reasons.\n");
}

// --- Configuration ---
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$webhook_url = getenv('TELEGRAM_WEBHOOK_URL');

if (!$bot_token || !$webhook_url) {
    die("Error: TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_URL must be set in your .env file.\n");
}

// --- API Call ---
$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($webhook_url);

echo "Setting webhook to: {$webhook_url}\n";

// Use file_get_contents for simplicity, as per "pure PHP" requirement.
// A more robust solution might use cURL.
$response_json = @file_get_contents($api_url);

if ($response_json === false) {
    die("Failed to make the request to Telegram API. Check your network connection and bot token.\n");
}

$response = json_decode($response_json, true);

// --- Output ---
if ($response && $response['ok'] === true) {
    echo "Webhook set successfully!\n";
    echo "Response: " . $response['description'] . "\n";
} else {
    echo "Failed to set webhook.\n";
    if ($response) {
        echo "Error Code: " . $response['error_code'] . "\n";
        echo "Description: " . $response['description'] . "\n";
    } else {
        echo "Invalid JSON response from Telegram.\n";
    }
}
