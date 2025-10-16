<?php
// --- Main API Router ---

// --- Standard API Entrypoint ---
// This brings in session management, CORS headers, and all helper functions.
require_once __DIR__ . '/api_header.php';

// --- Routing Logic ---
$requestUri = $_SERVER['REQUEST_URI'];
$queryParams = $_GET;

// Remove query string from URI to get the path.
$path = parse_url($requestUri, PHP_URL_PATH);

// --- Endpoint Mapping ---
// Maps clean URL paths to their corresponding handler files.
$endpointMap = [
    '/register' => 'register_user.php',
    '/login' => 'login_user.php',
    '/emails' => 'get_emails.php',
    '/is_user_registered' => 'check_email.php',
    '/check_auth' => 'check_email.php', // Alias for frontend compatibility
    '/upload' => 'email_handler.php', // Existing route for email uploads from the worker
    '/email_upload' => 'email_handler.php', // NEW: Corrected route for email uploads from the worker
    // Add other path-based routes here
];

// --- Route 1: Path-based API Routing ---
if (isset($endpointMap[$path])) {
    $handlerScript = __DIR__ . '/' . $endpointMap[$path];
    if (file_exists($handlerScript)) {
        // Set a standard header for JSON responses.
        header('Content-Type: application/json');
        // Include the specific endpoint handler.
        require_once $handlerScript;
        exit();
    }
} 
// --- Route 3: Simple Status Check for Root API path ---
elseif ($path === '/' || $path === '/api' || $path === '/api/') {
    header('Content-Type: application/json');
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Backend API is running.'
    ]);
    exit();
}

// --- Fallback: Not Found ---
// If no route matches, return a 404 error.
error_log("Backend API: Endpoint not found for path: " . $path . " and query: " . $requestUri); // Log unmatched routes
header('Content-Type: application/json');
http_response_code(404);
echo json_encode([
    'status' => 'error',
    'message' => 'Endpoint not found.'
]);

?>