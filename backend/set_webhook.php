<?php
// set_webhook.php
// This script sets up the Telegram bot webhook.
// Run this once from the command line after deploying your bot.

require_once 'config.php';

try {
    $botToken = Config::get('TELEGRAM_BOT_TOKEN');

    // Your server's public URL to the bot.php script
    $webhookUrl = 'https://wenge.cloudns.ch/bot.php';

    if (!$botToken || $botToken === 'YOUR_TELEGRAM_BOT_TOKEN') {
        throw new Exception("TELEGRAM_BOT_TOKEN is not configured in your .env file.");
    }

    // The URL for the Telegram setWebhook API
    $apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";

    // The parameters for the request (now without secret_token)
    $params = [
        'url' => $webhookUrl,
    ];

    // Build the final URL with query parameters
    $requestUrl = $apiUrl . '?' . http_build_query($params);

    echo "Setting webhook to:\n";
    echo "URL: {$webhookUrl}\n\n";

    // Send the request to the Telegram API
    $response = file_get_contents($requestUrl);

    // Decode the JSON response
    $responseData = json_decode($response, true);

    // Print the response from Telegram
    echo "Telegram API Response:\n";
    if ($responseData && $responseData['ok']) {
        echo "SUCCESS: " . $responseData['description'] . "\n";
    } else {
        echo "ERROR: Webhook could not be set.\n";
        echo $response . "\n";
    }

} catch (Exception $e) {
    echo "An error occurred:\n";
    echo $e->getMessage() . "\n";
}
?>
