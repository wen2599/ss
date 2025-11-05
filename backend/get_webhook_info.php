<?php
// get_webhook_info.php
// This script retrieves the current status of your bot's webhook from Telegram.

require_once 'config.php';

try {
    $botToken = Config::get('TELEGRAM_BOT_TOKEN');

    if (!$botToken || $botToken === 'YOUR_TELEGRAM_BOT_TOKEN') {
        throw new Exception("TELEGRAM_BOT_TOKEN is not configured in your .env file.");
    }

    // The URL for the Telegram getWebhookInfo API
    $apiUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";

    echo "Querying Telegram for webhook info...\n\n";

    // Send the request to the Telegram API
    $response = file_get_contents($apiUrl);

    // Decode the JSON response
    $responseData = json_decode($response, true);

    // Print the response from Telegram
    echo "Telegram API Response:\n";
    if ($responseData && $responseData['ok']) {
        echo json_encode($responseData['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "\n";
    } else {
        echo "ERROR: Could not retrieve webhook info.\n";
        echo $response . "\n";
    }

} catch (Exception $e) {
    echo "An error occurred:\n";
    echo $e->getMessage() . "\n";
}
?>
