<?php
// backend/set_webhook.php

// This script is intended to be run from the command line.
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

try {
    require_once 'config.php';

    // --- Configuration ---
    $bot_token = get_env_variable('TELEGRAM_BOT_TOKEN');
$webhook_secret = get_env_variable('TELEGRAM_WEBHOOK_SECRET');
$backend_url = get_env_variable('BACKEND_URL');

if (empty($bot_token) || empty($webhook_secret) || empty($backend_url)) {
    die("Error: Missing required environment variables (TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET, BACKEND_URL) in your .env file.\n");
}

// The URL of your webhook script
$webhook_url = rtrim($backend_url, '/') . '/webhook.php';

// Telegram API URL for setting the webhook
$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook";

// --- cURL Request ---
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $api_url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'url' => $webhook_url,
        'secret_token' => $webhook_secret,
        'allowed_updates' => ['message', 'channel_post'] // Specify desired updates
    ]),
]);

echo "Setting webhook to: {$webhook_url}\n";
$response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// --- Handle Response ---
if ($curl_error) {
    echo "cURL Error: {$curl_error}\n";
    exit(1);
}

if ($http_code !== 200) {
    echo "HTTP Error: Received status code {$http_code}\n";
}

$response = json_decode($response_json, true);

if (isset($response['ok']) && $response['ok']) {
    echo "Success! Webhook was set.\n";
    echo "Description: " . ($response['description'] ?? 'No description provided.') . "\n";
} else {
    echo "Error setting webhook.\n";
    echo "Response from Telegram: " . $response_json . "\n";
    exit(1);
}

exit(0);

} catch (Exception $e) {
    die("An unexpected error occurred: ". $e->getMessage(). "\n");
}
?>