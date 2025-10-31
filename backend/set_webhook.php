<?php
// backend/set_webhook.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Simplified .env loader ---
function get_env_variable($key) {
    $env_path = __DIR__ . '/../.env';
    if (!file_exists($env_path)) {
        return null;
    }
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            if (trim($name) === $key) {
                return trim($value);
            }
        }
    }
    return null;
}

$bot_token = get_env_variable('TELEGRAM_BOT_TOKEN');
$backend_url = get_env_variable('BACKEND_URL');

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
