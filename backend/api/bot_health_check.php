<?php
// A simple CLI script to check the health of the Telegram Bot.

// --- 1. Bootstrap the Application ---
// This includes environment variables, database connection, and error handling.
require_once __DIR__ . '/bootstrap.php';
echo "--- Telegram Bot Health Check ---\n\n";
echo "[INFO] Application bootstrapped.\n";


// --- 2. Check Environment Variables ---
echo "\n--- Checking Environment Variables ---\n";
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$channelId = $_ENV['TELEGRAM_CHANNEL_ID'] ?? null; // CORRECTED from LOTTERY_CHANNEL_ID

if ($botToken) {
    echo "[OK] TELEGRAM_BOT_TOKEN is set.\n";
} else {
    echo "[ERROR] TELEGRAM_BOT_TOKEN is NOT set. This is a fatal error.\n";
    exit(1);
}

if ($channelId) {
    echo "[OK] TELEGRAM_CHANNEL_ID is set.\n";
} else {
    echo "[WARNING] TELEGRAM_CHANNEL_ID is NOT set. The bot will not be able to parse results from the channel.\n";
}


// --- 3. Check Telegram API Connection ---
echo "\n--- Checking Telegram Webhook Status ---\n";
$url = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
$response = @file_get_contents($url);

if ($response === false) {
    echo "[ERROR] Could not connect to the Telegram API. Check network or firewall settings.\n";
    exit(1);
}

$data = json_decode($response, true);
if (!$data || ($data['ok'] ?? false) !== true) {
    echo "[ERROR] Invalid response from Telegram API: " . ($data['description'] ?? 'Unknown error') . "\n";
    exit(1);
}
echo "[SUCCESS] Successfully connected to the Telegram API.\n\n";

// --- 4. Display Webhook Info ---
$webhookInfo = $data['result'];
echo "Webhook URL: " . ($webhookInfo['url'] ?: 'Not Set') . "\n";
echo "Has Custom Certificate: " . ($webhookInfo['has_custom_certificate'] ? 'Yes' : 'No') . "\n";
echo "Pending Update Count: " . ($webhookInfo['pending_update_count'] ?? 0) . "\n";

if (isset($webhookInfo['last_error_date'])) {
    echo "Last Error Date: " . date('Y-m-d H:i:s', $webhookInfo['last_error_date']) . "\n";
    echo "Last Error Message: " . ($webhookInfo['last_error_message'] ?? 'None') . "\n";
}

echo "\n--- Health Check Complete ---\n";
