<?php
// set_webhook.php

// This script should be run from the command line in the 'backend' directory.
// Usage: php set_webhook.php

require_once __DIR__ . '/src/core/DotEnv.php';

// --- Configuration ---

// The public URL of your main router script
$backendUrl = 'https://wenge.cloudns.ch/index.php';

// The name of the endpoint for the Telegram webhook
$webhookEndpoint = 'telegram_webhook';

// --- Script Logic ---

echo "Attempting to set Telegram webhook...\n";

// 1. Load Environment Variables from .env file
try {
    $dotenv = new DotEnv(__DIR__ . '/.env');
    $env = $dotenv->getVariables();
} catch (\InvalidArgumentException $e) {
    die("Error: Could not find the .env file. Please ensure it exists in the 'backend' directory.\n");
}

$botToken = $env['TELEGRAM_BOT_TOKEN'] ?? null;

// 2. Validate the Bot Token
if (empty($botToken)) {
    die("Error: TELEGRAM_BOT_TOKEN is not set in your .env file.\n");
}

// 3. Construct the full webhook URL
$webhookUrl = $backendUrl . '?endpoint=' . $webhookEndpoint;

// 4. Construct the Telegram API URL for the setWebhook method
$apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);

echo "Setting webhook to: {$webhookUrl}\n";
echo "Calling Telegram API...\n";

// 5. Make the API call
$response = @file_get_contents($apiUrl);

// 6. Display the result
if ($response === false) {
    die("Error: Failed to connect to the Telegram API. Please check your internet connection and ensure the bot token is correct.\n");
}

$responseData = json_decode($response, true);

if (isset($responseData['ok']) && $responseData['ok']) {
    echo "Success! Webhook was set.\n";
    echo "Description: " . ($responseData['description'] ?? 'No description provided.') . "\n";
} else {
    echo "Error setting webhook:\n";
    echo "Error Code: " . ($responseData['error_code'] ?? 'N/A') . "\n";
    echo "Description: " . ($responseData['description'] ?? 'Unknown error.') . "\n";
}