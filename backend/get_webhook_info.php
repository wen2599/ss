<?php
// backend/get_webhook_info.php
// A standalone script to check the current Telegram webhook status.
// Run from the command line: php backend/get_webhook_info.php

// Load environment variables robustly.
require_once __DIR__ . '/lib/helpers.php';
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}
$dotenv_path = PROJECT_ROOT . '/.env';
if (file_exists($dotenv_path)) {
    load_env($dotenv_path);
} else {
    echo "CRITICAL ERROR: .env file not found at {$dotenv_path}. Please ensure it exists.\n";
    exit(1);
}

// Get the bot token.
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (empty($bot_token)) {
    echo "Error: TELEGRAM_BOT_TOKEN is not set in your .env file.\n";
    exit(1);
}

// Make the API call to Telegram.
$api_url = "https://api.telegram.org/bot{$bot_token}/getWebhookInfo";

echo "Querying Telegram for webhook info...\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Report the result.
if ($curl_error) {
    echo "cURL Error: {$curl_error}\n";
    exit(1);
}

if ($http_code !== 200) {
    echo "Telegram API returned HTTP status code: {$http_code}\n";
}

echo "Telegram API Response:\n";
$response_data = json_decode($response_json, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    echo $response_json;
}
echo "\n";

if ($response_data['ok'] && isset($response_data['result'])) {
    $result = $response_data['result'];
    if (empty($result['url'])) {
        echo "\n[!] WARNING: Webhook URL is not set!\n";
        echo "Run `php backend/set_telegram_webhook.php` to set it.\n";
    } else {
        echo "\n[✓] Webhook URL is set to: " . $result['url'] . "\n";
        if (isset($result['pending_update_count']) && $result['pending_update_count'] > 0) {
            echo "[!] PENDING UPDATES: " . $result['pending_update_count'] . "\n";
        }
        if (isset($result['last_error_date'])) {
            echo "[!] LAST ERROR DATE: " . date('Y-m-d H:i:s', $result['last_error_date']) . "\n";
            echo "[!] LAST ERROR MESSAGE: " . $result['last_error_message'] . "\n";
        }
    }
} else {
    echo "\n[!] Failed to get webhook info.\n";
}
?>