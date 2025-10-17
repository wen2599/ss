<?php
// backend/reset_webhook.php

// Enable full error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "--- Definitive Webhook Reset Script ---\n\n";

// --- Step 1: Load Environment Variables ---
echo "Step 1: Loading .env file...\n";
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath) || !is_readable($envPath)) {
    die("Error: .env file not found or is not readable at '{$envPath}'.\n");
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    die("Error: Could not read the .env file.\n");
}

foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . "=" . trim($value, "'\""));
    }
}
echo "OK: .env file loaded.\n\n";

// --- Step 2: Check Required Variables ---
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$webhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$backendUrl = getenv('BACKEND_URL');

if (!$botToken || !$webhookSecret || !$backendUrl) {
    die("Error: One or more required variables are missing from your .env file.\n");
}
echo "OK: All required variables are present.\n\n";

// --- Step 3: Delete Existing Webhook ---
echo "Step 3: Attempting to DELETE existing webhook...\n";
$deleteUrl = "https://api.telegram.org/bot{$botToken}/deleteWebhook";
$ch_delete = curl_init($deleteUrl);
curl_setopt($ch_delete, CURLOPT_RETURNTRANSFER, true);
$delete_response_body = curl_exec($ch_delete);
$delete_http_code = curl_getinfo($ch_delete, CURLINFO_HTTP_CODE);
curl_close($ch_delete);
$delete_response = json_decode($delete_response_body, true);

if ($delete_http_code === 200 && $delete_response['ok']) {
    echo "OK: Successfully deleted old webhook. Description: " . $delete_response['description'] . "\n\n";
} else {
    echo "Warning: Could not delete old webhook, or no webhook was set. This is usually safe to ignore. Proceeding...\n";
    echo "Details: " . $delete_response_body . "\n\n";
}

// --- Step 4: Set New Webhook with 'channel_post' ---
echo "Step 4: Attempting to SET new webhook with 'channel_post' updates...\n";
$webhookUrl = rtrim($backendUrl, '/') . '/telegramWebhook.php';
$telegramApiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";

$postData = [
    'url' => $webhookUrl,
    'secret_token' => $webhookSecret,
    'allowed_updates' => ['message', 'callback_query', 'channel_post']
];

$ch_set = curl_init($telegramApiUrl);
curl_setopt($ch_set, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_set, CURLOPT_POST, true);
curl_setopt($ch_set, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch_set, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch_set, CURLOPT_TIMEOUT, 20);

$set_response_body = curl_exec($ch_set);
$set_http_code = curl_getinfo($ch_set, CURLINFO_HTTP_CODE);
curl_close($ch_set);
$set_response = json_decode($set_response_body, true);

// --- Step 5: Final Analysis ---
echo "Step 5: Final analysis...\n";

if ($set_http_code === 200 && $set_response['ok']) {
    echo "--- DIAGNOSIS: SUCCESS! ---\n";
    echo "Telegram accepted the new webhook successfully.\n";
    echo "Description: " . $set_response['description'] . "\n\n";
    echo "The bot is now definitively configured to receive channel posts. Please have a new result posted in the channel to test.\n";
    echo "If this does not work, the issue is related to the bot's permissions within the channel itself, which must be checked on Telegram.\n";
} else {
    echo "--- DIAGNOSIS: FAILED ---\n";
    echo "Telegram rejected the new webhook.\n";
    echo "HTTP Status Code: " . $set_http_code . "\n";
    echo "Telegram Response Body:\n";
    echo print_r($set_response, true) . "\n";
}
?>