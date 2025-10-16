<?php
// backend/check_webhook.php

// Enable full error reporting for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "--- Webhook Diagnostic Script ---\n\n";

// --- Step 1: Load Environment Variables ---
echo "Step 1: Loading .env file...\n";
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath) || !is_readable($envPath)) {
    die("Error: .env file not found or is not readable at '{$envPath}'. Please ensure it exists and has correct permissions.\n");
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
        // Remove surrounding quotes
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
$backendUrl = getenv('BACKEND_URL'); // e.g., https://your.domain.com

if (!$botToken || $botToken === 'your_telegram_bot_token_here') {
    die("Error: TELEGRAM_BOT_TOKEN is not set in your .env file.\n");
}
echo "OK: TELEGRAM_BOT_TOKEN is set.\n";

if (!$webhookSecret) {
    die("Error: TELEGRAM_WEBHOOK_SECRET is not set in your .env file. Please generate a random, secure string for this.\n");
}
echo "OK: TELEGRAM_WEBHOOK_SECRET is set.\n";

if (!$backendUrl) {
    die("Error: BACKEND_URL is not set in your .env file. This should be the public base URL of your backend (e.g., https://api.example.com).\n");
}
echo "OK: BACKEND_URL is set.\n\n";

// --- Step 3: Construct Webhook URL ---
// The webhook endpoint is telegramWebhook.php
$webhookUrl = rtrim($backendUrl, '/') . '/telegramWebhook.php';
echo "Step 3: Constructed Webhook URL for Telegram...\n";
echo "URL: {$webhookUrl}\n\n";

// --- Step 4: Call Telegram's setWebhook API ---
echo "Step 4: Attempting to register this URL with Telegram...\n";

$telegramApiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
$postData = [
    'url' => $webhookUrl,
    'secret_token' => $webhookSecret,
    'allowed_updates' => ['message', 'callback_query'] // Specify desired updates
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
    echo "A cURL error occurred. This usually means your server cannot reach Telegram's API.\n";
    echo "Check for firewall issues or DNS problems on your server.\n";
    echo "cURL Error: " . $curlError . "\n";
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['ok']) && $responseData['ok'] === true) {
    echo "--- DIAGNOSIS: SUCCESS! ---\n";
    echo "Telegram accepted the webhook URL successfully.\n";
    echo "Description: " . $responseData['description'] . "\n\n";
    echo "Your bot should now be responsive. Please send it a /start command to test.\n";
} else {
    echo "--- DIAGNOSIS: FAILED ---\n";
    echo "Telegram rejected the webhook URL.\n";
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Telegram Response Body:\n";
    echo print_r($responseData, true) . "\n\n";
    echo "Common reasons for failure:\n";
    echo "1. The BACKEND_URL ('{$backendUrl}') is not publicly accessible from the internet.\n";
    echo "2. The URL does not use HTTPS, which is required by Telegram.\n";
    echo "3. A firewall is blocking Telegram's servers from reaching your webhook URL.\n";
}

// --- Step 6: Check Webhook Info ---
echo "\nStep 6: Fetching current webhook info for confirmation...\n";
$infoUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$infoResponse = file_get_contents($infoUrl);
echo "Current Info from Telegram:\n";
echo print_r(json_decode($infoResponse, true), true);
?>