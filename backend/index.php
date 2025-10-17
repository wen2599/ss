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
        // --- Debugging for 403 Forbidden issue --- 
        // Log all headers
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        error_log("Telegram Webhook Debug: All Headers: " . json_encode($headers));
        
        // Log raw request body
        $rawBody = file_get_contents('php://input');
        error_log("Telegram Webhook Debug: Raw Body: " . $rawBody);

        // Unconditionally return 200 OK to Telegram
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Webhook received, logging data.']);
        exit(); // Exit immediately after responding
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