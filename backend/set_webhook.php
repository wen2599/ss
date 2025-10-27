<?php
// backend/set_webhook.php

// This script is intended to be run once to set up the Telegram bot's webhook.
// For security, it's recommended to delete or restrict access to this file after use.

require_once __DIR__ . '/bootstrap.php';

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$secret_token = getenv('TELEGRAM_SECRET_TOKEN');

// The public URL to your bot.php script.
// This is based on the production URL from memory.
$webhook_url = 'https://wenge.cloudns.ch/bot.php';

header('Content-Type: text/plain');

if (!$bot_token || !$secret_token) {
    http_response_code(500);
    die("Error: TELEGRAM_BOT_TOKEN and TELEGRAM_SECRET_TOKEN must be set in your .env file.");
}

if (filter_var($webhook_url, FILTER_VALIDATE_URL) === false) {
    http_response_code(500);
    die("Error: The webhook URL '{$webhook_url}' is not valid.");
}

$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook";

$post_data = [
    'url' => $webhook_url,
    'secret_token' => $secret_token,
    'allowed_updates' => ['message', 'channel_post'] // Specify desired updates
];

echo "Attempting to set webhook to: {$webhook_url}\n";

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo "\n--- cURL Error ---\n" . $curl_error;
    } elseif ($http_code !== 200) {
        echo "\n--- Telegram API Error (HTTP Code: {$http_code}) ---\n";
        echo $response;
    } else {
        echo "\n--- Success! ---\n";
        echo "Webhook was set successfully.\n";
        echo "Response from Telegram:\n" . $response;
    }
} else {
    http_response_code(500);
    echo "\n--- Error ---\n";
    echo "cURL is not installed or enabled. This script requires cURL to function.";
}

echo "\n\nScript execution finished.\n";
