<?php
// backend/index.php - Front Controller

require_once __DIR__ . '/bootstrap.php'; // Load common functionalities

write_log("------ index.php Entry Point ------");

$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === null) {
    json_response('error', 'Missing endpoint parameter.', 400);
}

switch ($endpoint) {
    case 'register_user':
        require_once __DIR__ . '/register_user.php';
        break;
    case 'login_user':
        require_once __DIR__ . '/login_user.php';
        break;
    case 'logout_user':
        require_once __DIR__ . '/logout_user.php';
        break;
    case 'check_session':
        require_once __DIR__ . '/check_session.php';
        break;
    case 'get_emails':
        require_once __DIR__ . '/get_emails.php';
        break;
    case 'delete_bill':
        require_once __DIR__ . '/delete_bill.php';
        break;
    case 'get_bills': // Added new get_bills endpoint
        require_once __DIR__ . '/api/get_bills.php'; // Correct path for get_bills
        break;
    case 'get_lottery_results':
        require_once __DIR__ . '/get_lottery_results.php';
        break;
    case 'telegram_webhook':
        require_once __DIR__ . '/telegram_webhook.php';
        break;
    case 'process_email_ai':
        require_once __DIR__ . '/process_email_ai.php';
        break;
    case 'initialize_database': // Assuming this should also be routable for setup
        require_once __DIR__ . '/initialize_database.php';
        break;
    // Add other endpoints as needed
    default:
        json_response('error', 'Endpoint not found.', 404);
        break;
}

write_log("------ index.php Exit Point (unreached for successful endpoints) ------");
// This line should ideally not be reached if an endpoint successfully calls json_response and exits.
// However, it's harmless if reached due to logic flow not using json_response.
?>