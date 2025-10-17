<?php
// backend/set_webhook_final.php

// Enable full error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "--- Final Webhook Setup Script ---\n\n";
echo "This script will update your Telegram webhook to ensure it receives 'channel_post' updates.\n\n";

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
        $key = trim($key);
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}
echo "OK: .env file loaded.\n\n";

// --- Step 2: Check Required Variables ---
echo "Step 2: Checking for required environment variables...\n";
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$webhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$backendUrl = getenv('BACKEND_URL');

if (!$botToken || !$webhookSecret || !$backendUrl) {
    die("Error: One or more required variables (TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET, BACKEND_URL) are missing from your .env file.\n");
}
echo "OK: All required variables are present.\n\n";

// --- Step 3: Construct Webhook URL ---
$webhookUrl = rtrim($backendUrl, '/') . '/telegramWebhook.php';
echo "Step 3: Constructed Webhook URL for Telegram: {$webhookUrl}\n\n";

// --- Step 4: Call Telegram's setWebhook API with all required update types ---
echo "Step 4: Attempting to register webhook with 'channel_post' updates...\n";

$telegramApiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";

// *** THIS IS THE CRITICAL FIX ***
// We must explicitly ask for 'channel_post' to receive messages from channels.
$postData = [
    'url' => $webhookUrl,
    'secret_token' => $webhookSecret,
    'allowed_updates' => ['message', 'callback_query', 'channel_post']
];

$ch = curl_init($telegramApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --- Step 5: Analyze the Response ---
echo "Step 5: Analyzing Telegram's response...\n";

if ($curlError) {
    echo "--- DIAGNOSIS: FAILED ---\n";
    echo "A cURL error occurred: " . $curlError . "\n";
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['ok']) && $responseData['ok'] === true) {
    echo "--- DIAGNOSIS: SUCCESS! ---\n";
    echo "Telegram accepted the webhook URL successfully.\n";
    echo "Description: " . $responseData['description'] . "\n\n";
    echo "The bot is now configured to receive channel posts. Please have a new result posted in the channel to test.\n";
} else {
    echo "--- DIAGNOSIS: FAILED ---\n";
    echo "Telegram rejected the webhook URL.\n";
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Telegram Response Body:\n";
    echo print_r($responseData, true) . "\n";
}
?>