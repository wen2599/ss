<?php
// --- Set Telegram Webhook Script ---

// Enable full error reporting for debugging purposes
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Telegram Webhook Setup</h1>";

// --- Load Environment Variables ---
require_once __DIR__ . '/utils/config_loader.php';
echo "<p>Configuration loader included.</p>";

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$backend_url = getenv('BACKEND_URL');

// --- Validate Configuration ---
if (!$bot_token || !$backend_url) {
    echo "<p style='color:red; font-weight:bold;'>CRITICAL ERROR:</p>";
    echo "<p>The <code>TELEGRAM_BOT_TOKEN</code> or <code>BACKEND_URL</code> is not set in your .env file.</p>";
    echo "<p>Please ensure both variables are present and correct.</p>";
    exit;
}

echo "<p>Successfully loaded configuration.</p>";
echo "<ul>";
echo "<li><strong>Bot Token:</strong> <code>" . substr($bot_token, 0, 8) . "...</code> (hidden for security)</li>";
echo "<li><strong>Backend URL:</strong> <code>" . htmlspecialchars($backend_url) . "</code></li>";
echo "</ul>";

// --- Set Webhook ---
$webhook_script = 'telegram_webhook.php';
$webhook_url = rtrim($backend_url, '/') . '/' . $webhook_script;

echo "<p>Attempting to set webhook to: <code>" . htmlspecialchars($webhook_url) . "</code></p>";

$telegram_api_url = "https://api.telegram.org/bot" . $bot_token . "/setWebhook?url=" . urlencode($webhook_url);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $telegram_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response_json = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- Display Result ---
if ($http_code == 200) {
    $response_data = json_decode($response_json, true);
    if ($response_data && isset($response_data['ok']) && $response_data['ok'] === true) {
        echo "<h2 style='color:green; font-weight:bold;'>SUCCESS!</h2>";
        echo "<p>The Telegram webhook was set successfully.</p>";
        echo "<p><strong>Response from Telegram:</strong> " . htmlspecialchars($response_data['description']) . "</p>";
        echo "<p>The bot should now be correctly receiving updates. Please try sending a message to your channel again.</p>";
    } else {
        echo "<h2 style='color:red; font-weight:bold;'>API ERROR:</h2>";
        echo "<p>Telegram returned an error. This usually means the bot token is incorrect or the bot does not exist.</p>";
        echo "<pre>" . htmlspecialchars($response_json) . "</pre>";
    }
} else {
    echo "<h2 style='color:red; font-weight:bold;'>HTTP ERROR:</h2>";
    echo "<p>Could not connect to the Telegram API. The server returned HTTP status code: <code>" . $http_code . "</code></p>";
    echo "<p>This might be a temporary network issue or a problem with the server's ability to make outbound connections.</p>";
    echo "<pre>" . htmlspecialchars($response_json) . "</pre>";
}

?>
