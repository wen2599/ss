<?php
// backend/set_telegram_webhook.php
// A standalone script to programmatically set the Telegram webhook.
// This script is intentionally isolated from the main bootstrap to avoid database connections.
// Run this from the command line: php backend/set_telegram_webhook.php

// 1. Load only the necessary helper for environment variables.
require_once __DIR__ . '/lib/helpers.php';

// 2. Define the project root and load the .env file.
// The project root is two levels up from the current directory (`backend/`).
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}
$dotenv_path = PROJECT_ROOT . '/.env';
if (file_exists($dotenv_path)) {
    load_env($dotenv_path);
} else {
    echo "Error: .env file not found at {$dotenv_path}. Please ensure it exists.\n";
    exit(1);
}

// 3. Get the bot token from the environment.
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (empty($bot_token)) {
    echo "Error: TELEGRAM_BOT_TOKEN is not set in your .env file.\n";
    exit(1);
}

// 4. Define the webhook URL.
$backend_url = 'https://wenge.cloudns.ch/index.php';
$webhook_endpoint = 'telegram_webhook';
$webhook_url = "{$backend_url}?endpoint={$webhook_endpoint}";

// 5. Make the API call to Telegram.
$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url=" . urlencode($webhook_url);

echo "Attempting to set webhook to:\n{$webhook_url}\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// 6. Report the result.
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
    echo "\nWebhook set successfully! Your bot should now be responsive.\n";
} else {
    echo "\nWebhook setup failed. Please check the response above for details.\n";
}