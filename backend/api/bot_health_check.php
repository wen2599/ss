<?php
header('Content-Type: text/plain; charset=utf-8');

echo "--- Telegram Bot Health Check ---\n\n";

// --- 1. Bootstrap for Environment Variables ---
// We need to load the .env file to get the bot token.
require_once __DIR__ . '/bootstrap.php';
echo "[INFO] Application bootstrapped.\n";

// --- 2. Check Environment Variables ---
echo "\n--- Checking Environment Variables ---\n";
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
$channelId = $_ENV['LOTTERY_CHANNEL_ID'] ?? null;

if ($botToken) {
    echo "[OK] TELEGRAM_BOT_TOKEN is set.\n";
} else {
    echo "[ERROR] TELEGRAM_BOT_TOKEN is NOT set. The bot cannot function without this.\n";
}

if ($channelId) {
    echo "[OK] LOTTERY_CHANNEL_ID is set.\n";
} else {
    echo "[WARNING] LOTTERY_CHANNEL_ID is NOT set. The bot will not be able to parse results from the channel.\n";
}

if (!$botToken) {
    echo "\n[FATAL] Cannot proceed without a bot token. Please check your .env file.\n";
    exit;
}

// --- 3. Check Webhook Status via Telegram API ---
echo "\n--- Checking Telegram Webhook Status ---\n";

$apiUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";

try {
    $responseJson = file_get_contents($apiUrl);
    if ($responseJson === false) {
        throw new Exception("Failed to connect to the Telegram API. Check server's internet connection or DNS settings.");
    }

    $response = json_decode($responseJson, true);

    if (isset($response['ok']) && $response['ok'] === true) {
        echo "[SUCCESS] Successfully connected to the Telegram API.\n\n";
        $info = $response['result'];
        echo "Webhook URL: " . ($info['url'] ?: "Not Set") . "\n";
        echo "Has Custom Certificate: " . ($info['has_custom_certificate'] ? 'Yes' : 'No') . "\n";
        echo "Pending Update Count: " . $info['pending_update_count'] . "\n";

        if (isset($info['last_error_date'])) {
            echo "Last Error Date: " . date('Y-m-d H:i:s', $info['last_error_date']) . "\n";
            echo "Last Error Message: " . ($info['last_error_message'] ?? "N/A") . "\n";
        } else {
            echo "Last Error: None reported.\n";
        }

    } else {
        echo "[ERROR] Telegram API returned an error.\n";
        echo "Response: " . $responseJson . "\n";
    }

} catch (Exception $e) {
    echo "[FATAL] An exception occurred while contacting the Telegram API: " . $e->getMessage() . "\n";
}

echo "\n--- Health Check Complete ---\n";
