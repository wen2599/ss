<?php

// --- Telegram Webhook Setup Script ---
// This script should be run once from the command line after deployment.
// Usage: php set_webhook.php

// --- Load Configuration ---
require_once __DIR__ . '/config.php';

echo "--- Attempting to set Telegram Webhook ---\n";

// --- 1. Get Environment Variables ---
$token = getenv('TELEGRAM_BOT_TOKEN');
$secret = getenv('TELEGRAM_WEBHOOK_SECRET');
$backendUrl = getenv('BACKEND_PUBLIC_URL');

if (empty($token) || $token === 'your_telegram_bot_token_here') {
    echo "[FAILURE] TELEGRAM_BOT_TOKEN is not set in your .env file. Cannot proceed.\n";
    exit(1);
}
if (empty($secret)) {
    echo "[FAILURE] TELEGRAM_WEBHOOK_SECRET is not set in your .env file. Cannot proceed.\n";
    exit(1);
}
if (empty($backendUrl)) {
    echo "[FAILURE] BACKEND_PUBLIC_URL is not set in your .env file. Cannot proceed.\n";
    exit(1);
}
echo "  [SUCCESS] All required environment variables loaded.\n";


// --- 2. Define Webhook URL ---
$webhookUrl = rtrim($backendUrl, '/') . '/index.php?endpoint=telegramWebhook';
echo "  [INFO] Webhook URL determined as: {$webhookUrl}\n";


// --- 3. Make API Call to Set Webhook ---
$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";

$payload = [
    'url' => $webhookUrl,
    'secret_token' => $secret
];

echo "  [INFO] Calling Telegram API to set webhook with secret token...\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);


// --- 4. Report Result ---
if ($http_code === 200) {
    $responseData = json_decode($response, true);
    if ($responseData['ok'] === true) {
        echo "[SUCCESS] Webhook was set successfully with the secret token!\n";
        echo "  Description: " . $responseData['description'] . "\n";
    } else {
        echo "[FAILURE] Telegram API returned an error.\n";
        echo "  Description: " . $responseData['description'] . "\n";
    }
} else {
    echo "[FAILURE] Failed to connect to Telegram API.\n";
    echo "  HTTP Status Code: {$http_code}\n";
    echo "  Response: {$response}\n";
}

echo "\n--- Webhook setup finished. The bot should now be responsive. ---\n";

?>