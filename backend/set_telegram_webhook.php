<?php
// backend/set_telegram_webhook.php
// A simple script to programmatically set the Telegram webhook.
// Run this from the command line: php backend/set_telegram_webhook.php

require_once __DIR__ . '/bootstrap.php';

// --- Configuration ---
// Ensure the bot token is loaded from the environment.
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    echo "Error: TELEGRAM_BOT_TOKEN is not set in your .env file.\n";
    exit(1);
}

// The production URL of the backend router. This should not be changed.
$backend_url = 'https://wenge.cloudns.ch/index.php';

// The endpoint for the webhook, which must match a valid case in the worker.
$webhook_endpoint = 'telegram_webhook';

// --- Main Logic ---
// Construct the full, correct webhook URL. It MUST point to the index.php router.
$webhook_url = "{$backend_url}?endpoint={$webhook_endpoint}";

// The Telegram API URL for setting the webhook.
$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($webhook_url);

echo "Attempting to set webhook to:\n{$webhook_url}\n\n";

// Use cURL to send the request to the Telegram API.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// --- Output ---
if ($curl_error) {
    echo "cURL Error: {$curl_error}\n";
    exit(1);
}

if ($http_code !== 200) {
    echo "Telegram API returned HTTP status code: {$http_code}\n";
}

echo "Telegram API Response:\n";
$response_data = json_decode($response_json, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    echo $response_json;
}
echo "\n";

if ($response_data['ok'] ?? false) {
    echo "\nWebhook set successfully!\n";
    echo "Your bot should now be responsive.\n";
} else {
    echo "\nWebhook setup failed. Please check the response above for details.\n";
}