<?php

// Main entry point for API requests

// Ensure config.php (and thus .env) is loaded for environment variables and helper functions
require_once __DIR__ . '/api_header.php'; 

header('Content-Type: application/json');

$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing endpoint parameter.']);
    exit();
}

// All endpoints are assumed to be in the backend directory
switch ($endpoint) {
    case 'register_user':
        require_once __DIR__ . '/register_user.php';
        break;
    case 'login_user':
        require_once __DIR__ . '/login_user.php';
        break;
    case 'get_emails':
        require_once __DIR__ . '/get_emails.php';
        break;
    case 'delete_bill':
        require_once __DIR__ . '/delete_bill.php';
        break;
    case 'get_lottery_results':
        require_once __DIR__ . '/get_lottery_results.php';
        break;
    case 'telegramWebhook':
        // --- Telegram Webhook Secret Token Verification ---
        $expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
        $receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

        // Debugging logs
        error_log("Telegram Webhook Debug: Expected Secret (from env): [" . ($expectedSecret ? $expectedSecret : 'NOT SET') . "]");
        error_log("Telegram Webhook Debug: Received Secret (from header): [" . ($receivedSecret ? $receivedSecret : 'NOT SET') . "]");

        if (empty($expectedSecret)) {
            error_log("CRITICAL: TELEGRAM_WEBHOOK_SECRET is not set in environment.");
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => 'Server misconfiguration: Telegram secret not set.']);
            exit();
        }

        if ($receivedSecret !== $expectedSecret) {
            error_log("Telegram Webhook: ERROR - Invalid secret token provided. Received: " . ($receivedSecret ?? 'NOT SET') . ", Expected: " . $expectedSecret);
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Forbidden: Invalid secret token.']);
            exit();
        }
        // If verification passes, include the webhook handler
        require_once __DIR__ . '/telegramWebhook.php';
        break;
    case 'process_email_ai':
        require_once __DIR__ . '/process_email_ai.php';
        break;
    // Add other endpoints as needed
    default:
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Endpoint not found.']);
        break;
}

?>