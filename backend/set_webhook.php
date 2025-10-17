<?php
// A simple script to set the Telegram webhook.
// This helps in diagnosing connection issues from Telegram to our server.

// Load the configuration to get access to environment variables and helpers.
require_once __DIR__ . '/config.php';

// --- Configuration ---
$botToken = getenv('TELEGRAM_BOT_TOKEN');
$webhookSecret = getenv('TELEGRAM_WEBHOOK_SECRET');

// The public URL of our bot's entry point.
// IMPORTANT: This must match the front controller's routing.
$webhookUrl = 'https://ss.wenxiuxiu.eu.org/api/telegramWebhook';

// Check if the essential bot token is configured.
if (!$botToken || $botToken === 'your_telegram_bot_token_here') {
    die("Error: TELEGRAM_BOT_TOKEN is not configured in your .env file.\n");
}

echo "Attempting to set webhook for your bot...\n";
echo "Webhook URL: {$webhookUrl}\n";
echo "--------------------------------------------------\n";

// --- Build the Telegram API Request ---
$apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";

$postData = [
    'url' => $webhookUrl,
    // We send the secret token here for Telegram to include in the header of every update.
    'secret_token' => $webhookSecret,
];

// Use cURL to send the request.
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// --- Display the Result ---
if ($error) {
    echo "cURL Error: {$error}\n";
} else {
    echo "HTTP Status Code: {$http_code}\n";
    echo "Telegram API Response:\n";
    // Decode and re-encode for pretty printing.
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse) {
        echo json_encode($jsonResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        echo $response;
    }
    echo "\n";
}

echo "--------------------------------------------------\n";
if ($http_code === 200 && isset($jsonResponse['ok']) && $jsonResponse['ok'] === true) {
    echo "✅ Webhook set successfully!\n";
} else {
    echo "❌ Failed to set webhook. Please check the error message from Telegram above.\n";
    if (strpos($response, 'URL must be HTTPS') !== false) {
        echo "Hint: Ensure your webhook URL starts with 'https://'.\n";
    }
    if (strpos($response, 'invalid webhook URL') !== false) {
        echo "Hint: Telegram could not reach your URL. Check for firewall issues, DNS problems, or make sure the server is running and publicly accessible.\n";
    }
}
?>