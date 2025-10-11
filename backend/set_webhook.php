<?php
// A one-time script to set the Telegram webhook.
// From your project root (e.g., public_html), run: php backend/set_webhook.php

// --- DEPENDENCY LOADING ---
// Load the DotEnv class to read the .env file.
// This file is located in the `src/core` directory.
require_once __DIR__ . '/src/core/DotEnv.php';

// --- CONFIGURATION LOADING ---
// Load environment variables directly from the .env file in the 'backend' directory.
// This approach is robust and avoids the complex loading logic in the old config.php.
$dotenvPath = __DIR__ . '/.env';
if (!file_exists($dotenvPath)) {
    die("[ERROR] .env file not found at: {$dotenvPath}\nPlease create it based on .env.example.\n");
}
$dotenv = new DotEnv($dotenvPath);
$env = $dotenv->getVariables();

$botToken = $env['TELEGRAM_BOT_TOKEN'] ?? null;
$publicAppUrl = 'https://wenge.cloudns.ch'; // The backend domain.

// --- SCRIPT LOGIC (No need to edit below this line) ---

// --- VALIDATION ---
// Validate that the bot token is available
if (empty($botToken)) {
    die("[ERROR] TELEGRAM_BOT_TOKEN is not defined in your .env file.\n");
}

// Construct the full, CORRECT webhook URL.
// The '/backend' part is intentionally omitted because the web server's root
// is already pointing to the 'backend' directory, as per user instructions.
$webhookUrl = rtrim($publicAppUrl, '/') . '/public/index.php?endpoint=telegramWebhook';

// Prepare the API request to Telegram
$apiUrl = "https://api.telegram.org/bot" . $botToken . "/setWebhook?url=" . urlencode($webhookUrl);

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