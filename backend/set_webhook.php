<?php
// Standalone script to set the Telegram webhook URL.
// This should be run from the command line on your server, e.g., `php backend/set_webhook.php`

// Load the application's configuration and core functions
require_once __DIR__ . '/src/config.php';

// --- Webhook Configuration ---
// The URL MUST point to the public router.
$webhookUrl = 'https://wenge.cloudns.ch/public/index.php?endpoint=telegramWebhook';

// --- API Request ---
// Use the sendTelegramRequest function to call the setWebhook method.
$response = sendTelegramRequest('setWebhook', [
    'url' => $webhookUrl,
    'allowed_updates' => json_encode(['message', 'callback_query'])
]);

// --- Output ---
// Provide feedback to the console.
echo "Attempting to set webhook to: {$webhookUrl}\n";
echo "-------------------------------------------\n";
echo "Telegram API Response:\n";
print_r($response);
echo "-------------------------------------------\n";

if ($response && ($response['ok'] ?? false)) {
    echo "\nSUCCESS: Webhook set successfully!\n";
    echo "Your bot should now be responsive.\n";
} else {
    echo "\nERROR: Failed to set webhook.\n";
    echo "Reason: " . ($response['description'] ?? 'Unknown error') . "\n";
}