<?php
declare(strict_types=1);

// This is the entry point for the Telegram Bot Webhook.
error_log("webhook.php: Received a request.");

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/src/Services/TelegramService.php';
require_once __DIR__ . '/src/Controllers/BaseController.php';
require_once __DIR__ . '/src/Controllers/LotteryController.php';
require_once __DIR__ . '/src/Controllers/TelegramController.php';

// Security Check: Verify the secret token to ensure the request is from Telegram.
$secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expectedToken = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';

if (empty($secretToken) || empty($expectedToken) || !hash_equals($expectedToken, $secretToken)) {
    error_log("webhook.php: Unauthorized access attempt. Secret token did not match.");
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
error_log("webhook.php: Secret token verified.");

// Get the JSON payload from the request.
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    error_log("webhook.php: Failed to decode JSON update or update was empty.");
    http_response_code(400);
    echo 'Bad Request';
    exit;
}
error_log("webhook.php: JSON update decoded successfully.");

// Instantiate the controller and handle the webhook.
try {
    $telegramController = new \App\Controllers\TelegramController();
    $telegramController->handleWebhook($update);
    error_log("webhook.php: Webhook handled successfully.");
    // Acknowledge receipt to Telegram.
    echo 'OK';
} catch (Throwable $e) {
    // Use the global error logging from bootstrap.php.
    error_log('webhook.php: A fatal error occurred: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    // Return a 500 status to let Telegram know something went wrong.
    http_response_code(500);
    echo 'Internal Server Error';
}
