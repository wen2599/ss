<?php
// backend/setup_webhook.php
// This script programmatically sets the Telegram bot webhook.

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';

// --- Configuration ---
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$app_url = getenv('APP_URL'); // e.g., 'https://your-domain.com'

if (empty($bot_token)) {
    die("!!! FATAL ERROR: TELEGRAM_BOT_TOKEN is not defined in your .env file.\n");
}

if (empty($app_url)) {
    die("!!! FATAL ERROR: APP_URL is not defined in your .env file.\n");
}

// Ensure APP_URL uses HTTPS and does not have a trailing slash
if (strpos($app_url, 'http://') === 0) {
    die("!!! FATAL ERROR: APP_URL must use HTTPS. Telegram requires a secure webhook URL.\n");
}
if (strpos($app_url, 'https://') !== 0) {
    $app_url = 'https://' . $app_url;
}
$app_url = rtrim($app_url, '/');

$webhook_url = $app_url . '/backend/endpoints/tg_webhook.php';

// --- Telegram API Call ---
$api_url = "https://api.telegram.org/bot{" . $bot_token . "}/setWebhook?url=" . urlencode($webhook_url);

echo "--> Attempting to set webhook to: {$webhook_url}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- Output Result ---
echo "--> Telegram API responded with HTTP status code: {$http_code}\n";
echo "--> Response content:\n";

if ($output === false) {
    echo "!!! cURL Error: " . curl_error($ch) . "\n";
} else {
    $response = json_decode($output, true);
    if ($http_code == 200 && isset($response['ok']) && $response['ok'] === true) {
        echo "\n✅ SUCCESS! Webhook was set successfully.\n";
        echo "Description: " . ($response['description'] ?? 'No description provided.') . "\n";
        echo "\nYour bot should now be responsive.\n";
    } else {
        echo "\n!!! ERROR setting webhook.\n";
        echo "Raw Response: {$output}\n";
        echo "\nTroubleshooting Tips:\n";
        echo "1. Verify that your TELEGRAM_BOT_TOKEN in the .env file is correct.\n";
        echo "2. Ensure your domain '{$app_url}' is correct and publicly accessible.\n";
        echo "3. Make sure your server's firewall is not blocking requests from Telegram.\n";
    }
}

?>