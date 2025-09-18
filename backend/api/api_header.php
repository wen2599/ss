<?php
// Centralized error handling and configuration
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/config.php';

// Register the global error and exception handlers.
// This will catch any fatal errors and log them / return a clean JSON response.
register_error_handlers();

// --- CORS and HTTP Headers ---

// Allow requests from the specific frontend URL
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === FRONTEND_URL) {
    header('Access-Control-Allow-Origin: ' . FRONTEND_URL);
} else {
    // Optionally, you can handle requests from other origins, but for now we are strict.
    // For development, you might use '*' but it's insecure for production.
    // header('Access-Control-Allow-Origin: *');
}

// Specify that the client can send credentials (like cookies)
header('Access-Control-Allow-Credentials: true');

// Specify the allowed HTTP methods for requests
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Specify the allowed headers in a request
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Set the response content type to JSON for all API responses
header('Content-Type: application/json');

// Handle pre-flight requests (OPTIONS) sent by browsers
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // No further processing is needed for pre-flight requests
    exit(0);
}

// --- Session ---
// Include session configuration, which should now work correctly
require_once __DIR__ . '/session_config.php';
session_start();

?>
