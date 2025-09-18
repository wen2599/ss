<?php
// Centralized error handling and configuration
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/config.php';

// Register the global error and exception handlers.
// This will catch any fatal errors and log them / return a clean JSON response.
register_error_handlers();

// --- CORS Handling ---
// Dynamically set CORS headers to allow requests from the configured frontend URL.
if (defined('FRONTEND_URL') && !empty(FRONTEND_URL)) {
    // A list of allowed origins. We'll check the request's origin against this list.
    $allowed_origins = [
        FRONTEND_URL,
        // Also include common local development origins
        'http://localhost:5173', // Vite default
        'http://localhost:3000', // CRA default
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
    ];

    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Vary: Origin'); // Inform caches that the response may vary by Origin.
    }
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle pre-flight OPTIONS requests from browsers.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit();
}

// --- Headers ---
// We will still set the content type to JSON as a default for all API responses.
header('Content-Type: application/json');

// --- Session ---
// Include session configuration, which should now work correctly
require_once __DIR__ . '/session_config.php';
session_start();

?>
