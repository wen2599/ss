<?php

// This script fetches the current webhook status directly from Telegram's API.

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Standalone .env Loader ---
function load_env_for_check() {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        echo "Error: .env file not found at {$envPath}\n";
        exit(1);
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (strlen($value) > 1 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                $value = substr($value, 1, -1);
            }
            putenv("$name=$value");
        }
    }
}

echo "Loading environment variables...\n";
load_env_for_check();

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    echo "Error: TELEGRAM_BOT_TOKEN is not set in your .env file.\n";
    exit(1);
}
echo "Bot token loaded successfully.\n";

// --- API Call to Telegram ---
$url = "https://api.telegram.org/bot{$bot_token}/getWebhookInfo";

echo "Contacting Telegram API at: {$url}\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// --- Display Results ---
echo "\n============================================\n";
echo "   Telegram Webhook Status Report\n";
echo "============================================\n\n";

if ($http_code !== 200) {
    echo "HTTP Error: Failed to connect to Telegram API.\n";
    echo "HTTP Status Code: {$http_code}\n";
    if ($curl_error) {
        echo "cURL Error: {$curl_error}\n";
    }
    exit(1);
}

$response = json_decode($response_json, true);

if (!$response || !isset($response['ok']) || !$response['ok']) {
    echo "API Error: Telegram returned an error.\n";
    echo "Response:\n" . print_r($response, true) . "\n";
    exit(1);
}

$info = $response['result'];

echo "Webhook URL:                " . ($info['url'] ?: "Not Set") . "\n";
echo "Has Custom Certificate:     " . ($info['has_custom_certificate'] ? "Yes" : "No") . "\n";
echo "Pending Update Count:       " . $info['pending_update_count'] . "\n";

if (isset($info['ip_address'])) {
    echo "Webhook IP Address:         " . $info['ip_address'] . "\n";
}

if (isset($info['last_error_date'])) {
    echo "\n--- Last Error ---\n";
    echo "Last Error Date:            " . date('Y-m-d H:i:s T', $info['last_error_date']) . "\n";
    echo "Last Error Message:         " . $info['last_error_message'] . "\n";
} else {
    echo "\n✅ No recent errors reported by Telegram.\n";
}

if (isset($info['last_synchronization_error_date'])) {
    echo "\n--- Last Synchronization Error ---\n";
    echo "Last Sync Error Date:       " . date('Y-m-d H:i:s T', $info['last_synchronization_error_date']) . "\n";
}

echo "\n============================================\n";

?>