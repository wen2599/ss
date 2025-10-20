<?php
// backend/check_webhook.php
// A diagnostic script to check and manage the Telegram webhook.
// To use, run from the command line: php check_webhook.php [info|set|delete]

// Set context to command-line interface
if (php_sapi_name() !== 'cli') {
    die("This script is intended for command-line use only.");
}

echo "--- Telegram Webhook Diagnostic Tool ---\n";

// Load necessary files. bootstrap.php is needed for environment variables.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/telegram_helpers.php';

// Get the action from command line arguments.
$action = $argv[1] ?? 'info'; // Default to 'info'

// Define the webhook URL from environment variables.
$webhookUrl = rtrim($_ENV['BACKEND_PUBLIC_URL'], '/') . '/telegram_webhook.php';
$secretToken = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';

if (empty($_ENV['TELEGRAM_BOT_TOKEN'])) {
    echo "Error: TELEGRAM_BOT_TOKEN is not set in your .env file.\n";
    exit(1);
}
if (empty($webhookUrl)) {
    echo "Error: BACKEND_PUBLIC_URL is not set in your .env file.\n";
    exit(1);
}

switch ($action) {
    case 'info':
        echo "Fetching current webhook information...\n";
        $response = getTelegramWebhookInfo();
        if ($response['ok']) {
            echo "Current Webhook URL: " . ($response['result']['url'] ?? 'Not Set') . "\n";
            echo "Pending Updates: " . ($response['result']['pending_update_count'] ?? 'N/A') . "\n";
            if (!empty($response['result']['last_error_date'])) {
                echo "Last Error Date: " . date('Y-m-d H:i:s', $response['result']['last_error_date']) . "\n";
                echo "Last Error Message: " . ($response['result']['last_error_message'] ?? 'N/A') . "\n";
            }
        } else {
            echo "Error fetching webhook info: " . $response['description'] . "\n";
        }
        break;

    case 'set':
        echo "Setting webhook to: $webhookUrl\n";
        if (empty($secretToken)) {
            echo "Warning: TELEGRAM_WEBHOOK_SECRET is not set. It is highly recommended for security.\n";
        }
        $response = setTelegramWebhook($webhookUrl, $secretToken);
        if ($response['ok']) {
            echo "Success! Webhook was set.\n";
            echo "Description: " . $response['description'] . "\n";
        } else {
            echo "Error setting webhook: " . $response['description'] . "\n";
        }
        break;

    case 'delete':
        echo "Deleting current webhook...\n";
        $response = deleteTelegramWebhook();
        if ($response['ok']) {
            echo "Success! Webhook was deleted.\n";
            echo "Description: " . $response['description'] . "\n";
        } else {
            echo "Error deleting webhook: " . $response['description'] . "\n";
        }
        break;

    default:
        echo "Invalid action. Use one of: info, set, delete\n";
        break;
}

echo "\n--- Diagnostic Tool Finished ---\n";
