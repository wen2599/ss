<?php
// index.php (FIXED AND COMPLETE)

// Main entry point for API requests

header('Content-Type: application/json');

// --- Pre-flight request for CORS (important for some setups) ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *"); // Be more specific in production if needed
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    exit(0);
}

// All endpoints are assumed to be in the same directory
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
    case 'logout_user': // <-- MISSING CASE ADDED
        require_once __DIR__ . '/logout_user.php';
        break;
    case 'check_session': // <-- MISSING CASE ADDED
        require_once __DIR__ . '/check_session.php';
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
    case 'telegram_webhook':
        require_once __DIR__ . '/telegram_webhook.php';
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
