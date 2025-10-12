<?php

// --- Telegram Webhook Setup Script ---
// This script should be run once from the command line after deployment.
// Usage: php backend/set_webhook.php

// --- Load Configuration ---
// We need the config file to load the .env variables, especially the bot token.
require_once __DIR__ . '/config.php';

echo "--- Attempting to set Telegram Webhook ---\n";

// --- 1. Get Bot Token ---
$token = getenv('TELEGRAM_BOT_TOKEN');
if (empty($token) || $token === 'your_telegram_bot_token_here') {
    echo "[FAILURE] TELEGRAM_BOT_TOKEN is not set in your .env file. Cannot proceed.\n";
    exit(1);
}
echo "  [SUCCESS] Bot token loaded.\n";


// --- 2. Define Webhook URL ---
// This is the public URL where Telegram will send updates.
// It must point to the main router and specify the telegramWebhook endpoint.
$backendUrl = getenv('BACKEND_PUBLIC_URL'); // e.g., https://wenge.cloudns.ch
if (empty($backendUrl)) {
    echo "[FAILURE] BACKEND_PUBLIC_URL is not set in your .env file. Please add it.\n";
    echo "          Example: BACKEND_PUBLIC_URL=\"https://your.domain.com\"\n";
    exit(1);
}

// Ensure the URL doesn't have a trailing slash, then add the path to the webhook handler.
$webhookUrl = rtrim($backendUrl, '/') . '/index.php?endpoint=telegramWebhook';
echo "  [INFO] Webhook URL determined as: {$webhookUrl}\n";


// --- 3. Make API Call to Set Webhook ---
$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhookUrl);

echo "  [INFO] Calling Telegram API to set webhook...\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);


// --- 4. Report Result ---
if ($http_code === 200) {
    $responseData = json_decode($response, true);
    if ($responseData['ok'] === true) {
        echo "[SUCCESS] Webhook was set successfully!\n";
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

echo "\n--- Webhook setup finished. ---\n";

?>