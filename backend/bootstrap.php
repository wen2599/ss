<?php
// backend/bootstrap.php

// --- Early CORS Preflight Handling ---
// Respond to OPTIONS requests immediately to prevent CORS errors if the backend crashes.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");
    http_response_code(204); // No Content
    exit();
}

// Use a constant to ensure the main setup is only run once per request.
if (defined('BOOTSTRAP_INITIALIZED')) {
    return;
}
define('BOOTSTRAP_INITIALIZED', true);


// --- Main Bootstrap Logic with Master Error Handling ---
try {
    // --- Global Error and Exception Handling ---
    // These functions ensure that any error, from a warning to a fatal crash,
    // is caught and logged, returning a standardized JSON response.

    function global_exception_handler($exception) {
        // Prevent sending multiple error responses
        if (headers_sent()) {
            error_log('Headers already sent, cannot send JSON error response for exception.');
            return;
        }

        http_response_code(500);
        header('Content-Type: application/json');

        // Log detailed error information for debugging.
        error_log(
            "Uncaught Exception: " . $exception->getMessage() .
            " in " . $exception->getFile() . ":" . $exception->getLine() .
            "\nStack trace:\n" . $exception->getTraceAsString()
        );

        // Return a generic, safe error message to the client.
        echo json_encode(['status' => 'error', 'message' => 'An unexpected server error occurred.']);
        exit();
    }

    function fatal_error_shutdown_handler() {
        $last_error = error_get_last();
        if ($last_error && in_array($last_error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            // Convert the fatal error into an ErrorException and pass to the global handler.
            global_exception_handler(new ErrorException($last_error['message'], 0, $last_error['type'], $last_error['file'], $last_error['line']));
        }
    }

    function error_to_exception_handler($severity, $message, $file, $line) {
        // Respect the error_reporting level.
        if (!(error_reporting() & $severity)) {
            return;
        }
        // Throw an exception for all handled errors.
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    // Register the handlers.
    set_exception_handler('global_exception_handler');
    set_error_handler('error_to_exception_handler');
    register_shutdown_function('fatal_error_shutdown_handler');

    // Configure PHP error reporting for production.
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);


    // --- Environment & Database ---
    require_once __DIR__ . '/load_env.php';

    global $pdo;
    if (!isset($pdo)) {
        // Check for required PHP extensions.
        if (!class_exists('PDO')) {
            throw new Exception('Server configuration error: PDO extension is not available.');
        }

        // Load database credentials securely from environment variables.
        $db_host = $_ENV['DB_HOST'] ?? 'localhost';
        $db_port = $_ENV['DB_PORT'] ?? '3306';
        $db_database = $_ENV['DB_DATABASE'] ?? '';
        $db_username = $_ENV['DB_USERNAME'] ?? '';
        $db_password = $_ENV['DB_PASSWORD'] ?? '';

        if (empty($db_database) || empty($db_username)) {
            throw new Exception('Database credentials are not configured.');
        }

        // Establish the database connection.
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_database};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_username, $db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    // --- Final CORS Headers ---
    // These are sent on actual API responses.
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Credentials: true");


    // --- Session Handling ---
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

} catch (Throwable $e) {
    // This is the master catch block. If any part of the setup fails,
    // this will catch it, log it, and return a clean 500 error.
    global_exception_handler($e);
}
