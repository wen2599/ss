<?php

// --- EXTREME TEMPORARY DEBUGGING BLOCK START (backend/index.php) ---
// This block is to check if PHP script can execute AT ALL and output to browser.

echo "Hello from backend/index.php - This is a very early test.";
exit; // Force script to exit immediately after outputting this message.

// --- EXTREME TEMPORARY DEBUGGING BLOCK END ---

// Original backend/index.php content (commented out for this test)
/*

header('Content-Type: application/json');

$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing endpoint parameter.']);
    exit();
}

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

*/

?>