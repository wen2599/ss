<?php
// backend/api.php - Main API Gateway

// --- Session Initialization ---
// Must be called before any output is sent to the browser.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$allowed_origins = ['https://ss.wenxiuxiu.eu.org', 'http://localhost:5173'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}

// Handle CORS pre-flight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    exit(0);
}

// Set CORS headers for the actual request
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");


// require_once __DIR__ . '/bootstrap.php';
// require_once __DIR__ . '/helpers.php';
// require_once __DIR__ . '/api/EmailController.php';
// require_once __DIR__ . '/api/AuthController.php';

global $db_connection;

// Determine the component to handle the request based on the context.
// For simplicity, we can inspect the 'action' parameter.
$action = $_REQUEST['action'] ?? null;

if (!$action) {
    // Fallback for older auth requests that might send JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? null;
}

// Route to the appropriate controller
switch ($action) {
    // Email Actions
    case 'save_email':
    case 'is_user_registered':
        $emailController = new EmailController($db_connection);
        $emailController->handleRequest();
        break;

    // Auth Actions
    case 'login':
    case 'register':
    case 'logout':
    case 'check_session':
        // AuthController expects JSON input, which is handled correctly
        $authController = new AuthController($db_connection);
        $authController->handleRequest();
        break;

    // AI Processing Action
    case 'process_email_ai':
        require_once __DIR__ . '/api/ai_process_email.php'; // The script itself handles the request.
        break;

    default:
        sendJsonResponse(400, ['success' => false, 'message' => 'No valid action specified.']);
        break;
}
