<?php
// backend/api.php - Main API Gateway

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers.php'; // Ensure helpers.php is included for sendJsonResponse
require_once __DIR__ . '/api/EmailController.php';
require_once __DIR__ . '/api/AuthController.php';

global $db_connection;

header("Content-Type: application/json");

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
