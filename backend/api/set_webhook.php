<?php
declare(strict_types=1);

// This script is intended to be run once from a web browser to set the Telegram webhook.
// For security, it should be deleted from the server after successful execution.

require __DIR__ . '/vendor/autoload.php';

// --- Configuration ---
$envPath = __DIR__ . '/../';
$logFile = $envPath . 'logs/webhook_setup.log';

// --- Logging Function ---
function logMessage(string $message, string $file): void {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($file, "[$timestamp] $message\n", FILE_APPEND);
}

// --- Main Execution ---
header('Content-Type: text/plain');

try {
    // 1. Load Environment Variables
    if (!file_exists($envPath . '.env')) {
        throw new Exception("Error: .env file not found. Please ensure it exists in the 'backend' directory.");
    }
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->load();
    logMessage("Successfully loaded .env file.", $logFile);

    // 2. Validate Essential Variables
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
    $backendUrl = $_ENV['BACKEND_URL'] ?? null;

    if (empty($botToken)) {
        throw new Exception("Error: TELEGRAM_BOT_TOKEN is not set in the .env file.");
    }
    if (empty($backendUrl)) {
        throw new Exception("Error: BACKEND_URL is not set in the .env file. This should be the public URL of your backend.");
    }
    logMessage("Found Bot Token and Backend URL.", $logFile);


    // 3. Construct the Webhook URL
    // Ensure the URL has a trailing slash, then append the relative path.
    $webhookUrl = rtrim($backendUrl, '/') . '/api/telegram-webhook';
    logMessage("Constructed Webhook URL: $webhookUrl", $logFile);

    // 4. Construct the Telegram API Request URL
    $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
    logMessage("Calling Telegram API: $telegramApiUrl", $logFile);

    // 5. Execute the API Call using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $telegramApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $responseJson = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL Error: " . $curlError);
    }

    // 6. Process the Response
    $response = json_decode($responseJson, true);

    if ($httpCode !== 200 || !isset($response['ok']) || $response['ok'] !== true) {
        $description = $response['description'] ?? 'No description provided.';
        $errorCode = $response['error_code'] ?? 'N/A';
        throw new Exception("Telegram API returned an error (HTTP Code: $httpCode). Error {$errorCode}: {$description}");
    }

    // 7. Success!
    $successMessage = "✅ Success! The Telegram webhook has been set to:\n{$webhookUrl}\n\nTelegram's response:\n" . json_encode($response, JSON_PRETTY_PRINT);
    logMessage($successMessage, $logFile);
    echo $successMessage;
    echo "\n\nIMPORTANT: For security, please delete this script (set_webhook.php) from your server now.";

} catch (Throwable $e) {
    $errorMessage = "❌ An error occurred: " . $e->getMessage();
    logMessage($errorMessage, $logFile);
    http_response_code(500);
    echo $errorMessage;
}
