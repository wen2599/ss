<?php
// File: backend/index.php (Final Version)

/**
 * Main API Entry Point.
 * This script is responsible for loading core dependencies and routing
 * requests to the appropriate endpoint handlers.
 */

// --- 1. Load ALL core dependencies in one place, in the correct order ---

// config.php defines the config() helper function and sets up error logging.
require_once __DIR__ . '/config.php';

// db_operations.php defines get_db_connection() and uses the config() helper.
require_once __DIR__ . '/db_operations.php';

// api_header.php handles CORS headers, session start, and content type. It also uses config().
require_once __DIR__ . '/api_header.php';


// --- 2. Get endpoint parameter from the URL ---
$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing endpoint parameter.']);
    exit();
}


// --- 3. Define the routing map ---
// This array maps the 'endpoint' string to the physical PHP file.
$routes = [
    // Authentication endpoints
    'register'              => 'auth/register.php',
    'login'                 => 'auth/login.php',
    'logout'                => 'auth/logout.php',
    'check_session'         => 'auth/check_session.php',

    // Email related endpoints
    'get_emails'            => 'auth/get_emails.php',
    'get_email_details'     => 'auth/get_email_details.php',
    'update_bet_batch'      => 'auth/update_bet_batch.php',
    
    // Lottery related endpoints
    'get_lottery_results'       => 'lottery/get_results.php',
    'get_lottery_result_by_issue' => 'lottery/get_result_by_issue.php',

    // Settlement related endpoints (Placeholder for future)
    // 'run_settlement'      => 'settlement/run.php',
    // 'get_settlements'     => 'settlement/get_list.php',
];


// --- 4. Route the request ---
if (isset($routes[$endpoint])) {
    // If the endpoint is valid, include the corresponding handler file.
    // The handler file will then take over and produce the output.
    require_once __DIR__ . '/' . $routes[$endpoint];
} else {
    // If the endpoint is not found in our map, return a 404 error.
    http_response_code(404); // Not Found
    echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
}