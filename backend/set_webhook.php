<?php
// A one-time script to set the Telegram webhook.
// From your project root (e.g., public_html), run: php backend/set_webhook.php

// --- Bootstrap Application ---
// This single line loads all configurations, core libraries, and error handlers.
require_once __DIR__ . '/src/config.php';

// --- CONFIGURATION ---
$publicAppUrl = 'https://wenge.cloudns.ch'; // The backend domain.

// --- SCRIPT LOGIC (No need to edit below this line) ---

// --- VALIDATION ---
// Validate that the bot token is available (it's loaded as a constant from config.php)
if (empty(TELEGRAM_BOT_TOKEN)) {
    die("[ERROR] TELEGRAM_BOT_TOKEN is not defined. Please check your .env file.\n");
}

// Construct the full, CORRECT webhook URL.
// The '/backend' part is intentionally omitted because the web server's root
// is already pointing to the 'backend' directory.
$webhookUrl = rtrim($publicAppUrl, '/') . '/public/index.php?endpoint=telegramWebhook';

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