<?php
$request_uri = strtok($_SERVER['REQUEST_URI'], '?');
$api_path = '/api/';

// Basic router
if (strpos($request_uri, $api_path) === 0) {
    $endpoint = substr($request_uri, strlen($api_path));
    
    switch ($endpoint) {
        case 'users/register':
        case 'users/login':
            require __DIR__ . '/api/users.php';
            break;

        case 'emails/list':
            require __DIR__ . '/api/emails.php';
            break;

        // Add more public API endpoints here
        
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} else {
    // Handle internal or other paths if needed
    switch ($request_uri) {
        case '/internal/receive_email':
             require __DIR__ . '/internal/receive_email.php';
             break;
        case '/internal/telegram_webhook':
             require __DIR__ . '/internal/telegram_webhook.php';
             break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
            break;
    }
}
