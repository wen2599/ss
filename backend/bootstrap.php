<?php
// backend/bootstrap.php

// ############################################################################
// CORS Preflight Request Handling
// ############################################################################
// This block must be at the very top of the script, before any other includes
// or logic. This ensures that the preflight request is handled successfully
// even if the rest of the application fails to load.

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    http_response_code(204); // No Content
    exit;
}

// ############################################################################
// Standard CORS Headers
// ############################################################################
// These headers are sent for all non-preflight requests.
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Credentials: true");


// Basic configuration and database connection.

// Load environment variables from .env file
require_once __DIR__ . '/load_env.php';

// --- Database Configuration ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// --- Global Settings ---
// Set the default timezone.
date_default_timezone_set('UTC');

// Enable error reporting for development.
// In a production environment, you should log errors to a file instead.
ini_set('display_errors', 0); // Disable public error display
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', __DIR__ . '/../php_errors.log'); // Specify log file
error_reporting(E_ALL);

// ############################################################################
// Global Exception and Error Handling
// ############################################################################

function handle_exception(Throwable $e) {
    // Log the detailed error
    error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\nStack trace: " . $e->getTraceAsString());

    // Send a generic, user-friendly JSON error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'An internal server error occurred.']);
    exit;
}

function handle_fatal_error() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Log the detailed error
        error_log("Fatal error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);

        // Send a generic, user-friendly JSON error response
        // Note: Headers might have already been sent. This is a best-effort attempt.
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'A critical server error occurred.']);
        }
    }
}

set_exception_handler('handle_exception');
register_shutdown_function('handle_fatal_error');


// --- Database Connection ---
// Establish a connection to the MySQL database using PDO.

// First, check if the PDO class exists. A silent fatal error occurs if it doesn't.
if (!class_exists('PDO')) {
    // Manually trigger our exception handler to ensure a clean JSON response.
    handle_exception(new Exception('Server Configuration Error: The PDO class is not available. This is required for database operations.'));
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD
    );
    // Set PDO to throw exceptions on error.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // The most common cause of a total application failure is a bad DB connection.
    // We catch this specific exception to provide a more helpful error message
    // than the generic "Internal Server Error".
    // We log the real error for debugging, but send a clean message to the user.
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please check your backend/.env file.']);
    exit;
}

// --- Session Management ---
// Configure session cookie for cross-domain access before starting the session.
// 'SameSite=None' and 'secure=true' are required for the frontend on a different
// domain to successfully maintain a session via cookies.
session_set_cookie_params([
    'samesite' => 'None',
    'secure' => true,
    'httponly' => true
]);

// Start or resume a session.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Headers ---
// Set common security headers.
// Note: These might be better handled by your web server (Nginx/Apache) in production.
header("Content-Security-Policy: default-src 'self'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

?>
