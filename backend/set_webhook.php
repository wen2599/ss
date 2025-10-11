<?php
// A one-time script to set the Telegram webhook.
// From your terminal, run: php backend/set_webhook.php

// Load the bot configuration (which should include the token)
require_once __DIR__ . '/src/config.php';

// --- CONFIGURATION ---
// !!! IMPORTANT !!!
// Replace the placeholder below with the actual, public HTTPS URL of your application.
// Telegram will send all bot updates to this address.
$publicAppUrl = 'https://<REPLACE-WITH-YOUR-PUBLIC-APP-URL>';


// --- SCRIPT LOGIC (No need to edit below this line) ---

// Construct the full webhook URL
$webhookUrl = rtrim($publicAppUrl, '/') . '/backend/public/index.php?endpoint=telegramWebhook';

// Validate that the bot token is available
if (empty(TELEGRAM_BOT_TOKEN)) {
    die("[ERROR] TELEGRAM_BOT_TOKEN is not defined. Please check your configuration in 'backend/src/config.php'.\n");
}

// Validate that the placeholder URL has been changed
if (strpos($webhookUrl, '<REPLACE-WITH-YOUR-PUBLIC-APP-URL>') !== false) {
    die("[ACTION REQUIRED] Please edit the file 'backend/set_webhook.php' and replace the placeholder URL with your actual public application URL.\n");
}

// Prepare the API request to Telegram
$apiUrl = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook?url=" . urlencode($webhookUrl);

printf("Attempting to set webhook to: %s\n", $webhookUrl);

// Use cURL to send the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$responseJson = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

// Process the response
if ($error) {
    die("[ERROR] cURL failed: " . $error . "\n");
}

$response = json_decode($responseJson, true);

if ($response && $response['ok']) {
    echo "[SUCCESS] Webhook was set successfully!\n";
    echo "Description: " . $response['description'] . "\n";
} else {
    echo "[ERROR] Failed to set webhook.\n";
    echo "Telegram's response: " . $responseJson . "\n";
}

?>