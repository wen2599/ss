<?php
// backend/api.php - Main API Gateway
session_start();
// Handle CORS pre-flight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    exit(0);
}

// Allow requests from any origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");


require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers.php'; // Ensure helpers.php is included for sendJsonResponse
require_once __DIR__ . '/api/EmailController.php';
require_once __DIR__ . '/api/AuthController.php';

global $db_connection;

// Read the raw request body once to avoid stream exhaustion
$data = json_decode(file_get_contents('php://input'), true) ?? [];

// Determine the action from the JSON body or fallback to query parameters
$action = $data['action'] ?? $_REQUEST['action'] ?? null;
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
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
        $authController = new AuthController($db_connection);
        $authController->handleRequest($data); // Pass the decoded data
        break;

    // AI Processing Action
    case 'process_email_ai':
        require_once __DIR__ . '/api/ai_process_email.php'; // The script itself handles the request.
        break;

    default:
        sendJsonResponse(400, ['success' => false, 'message' => 'No valid action specified.']);
        break;
}
