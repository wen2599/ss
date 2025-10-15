<?php

// --- Webhook Configuration Checker ---
// This script helps diagnose and fix webhook setup issues.

header('Content-Type: text/plain');

// Load environment variables from .env file
require_once __DIR__ . '/config.php';

echo "--- Webhook Configuration Check ---\n\n";

// 1. Get required variables from environment
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$webhook_secret = getenv('TELEGRAM_WEBHOOK_SECRET');
$backend_url = 'https://' . $_SERVER['HTTP_HOST']; // Assumes script is run on the production server

// 2. Validate the variables
$all_vars_ok = true;
if (empty($bot_token) || $bot_token === 'your_telegram_bot_token_here') {
    echo "❌ ERROR: TELEGRAM_BOT_TOKEN is not set or is set to the default value.\n";
    echo "   Please set it correctly in your .env file.\n\n";
    $all_vars_ok = false;
} else {
    echo "✅ TELEGRAM_BOT_TOKEN is set.\n";
}

if (empty($webhook_secret)) {
    echo "❌ ERROR: TELEGRAM_WEBHOOK_SECRET is not set.\n";
    echo "   Please generate a strong, random secret and add it to your .env file.\n";
    echo "   Example: " . bin2hex(random_bytes(16)) . "\n\n";
    $all_vars_ok = false;
} else {
    echo "✅ TELEGRAM_WEBHOOK_SECRET is set.\n";
}

// 3. Construct the webhook URL
$webhook_url = $backend_url . '/telegramWebhook.php';
echo "✅ Your full webhook URL is: " . $webhook_url . "\n\n";

// 4. Generate the command to set the webhook
if ($all_vars_ok) {
    echo "--- To fix your webhook, run the following command in your terminal ---\n";
    echo "This command tells Telegram where to send updates and provides the secret token for verification.\n\n";

    $api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook";

    // Use escapeshellarg to ensure the parameters are safely passed to the command line
    $command = 'curl -F "url=' . escapeshellarg($webhook_url) . '" -F "secret_token=' . escapeshellarg($webhook_secret) . '" ' . escapeshellarg($api_url);

    echo $command;
    echo "\n\n";
    echo "After running this command, your bot should become responsive.\n";

} else {
    echo "--- Please fix the errors above before you can set the webhook. ---\n";
}

?>