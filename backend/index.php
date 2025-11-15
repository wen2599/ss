<?php
// File: backend/index.php (路由更新)

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
    'reanalyze_email'       => 'auth/reanalyze_email.php',
    'download_settlement'   => 'auth/download_settlement.php',
    'smart_parse_email'     => 'auth/smart_parse_email.php',
    'parse_single_bet'      => 'auth/parse_single_bet.php',
    'split_email_lines'     => 'auth/split_email_lines.php',
    'calibrate_ai_parse'    => 'auth/calibrate_ai_parse.php',
    'quick_calibrate_ai'    => 'auth/quick_calibrate_ai.php', // <-- 新增快速校准路由

    // Lottery related endpoints
    'get_lottery_results'       => 'lottery/get_results.php',
    'get_lottery_result_by_issue' => 'lottery/get_result_by_issue.php',

    // Odds template endpoints
    'odds_template'         => 'auth/odds_template.php',
];


// --- 4. Route the request ---
if (isset($routes[$endpoint])) {
    // If the endpoint is valid, include the corresponding handler file.
    require_once __DIR__ . '/' . $routes[$endpoint];
} else {
    // If the endpoint is not found in our map, return a 404 error.
    http_response_code(404); // Not Found
    echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
}

// --- 5. Log the request for debugging ---
if (defined('MAIL_LOG_FILE')) {
    $log_message = sprintf(
        "API Request: %s %s - Endpoint: %s - User: %s\n",
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        $endpoint ?? 'none',
        $_SESSION['user_id'] ?? 'guest'
    );
    error_log($log_message, 3, MAIL_LOG_FILE);
}
?>