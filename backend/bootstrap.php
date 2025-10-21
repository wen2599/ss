<?php
// backend/bootstrap.php

// Use a constant to ensure this script's initial setup is only run once.
if (!defined('BOOTSTRAP_INITIALIZED')) {
    define('BOOTSTRAP_INITIALIZED', true);

    // --- Global Error and Exception Handling ---
    if (!function_exists('global_exception_handler')) {
        function global_exception_handler($exception) {
            if (headers_sent()) {
                error_log('Headers already sent, cannot send JSON error response.');
                return;
            }
            http_response_code(500);
            header('Content-Type: application/json');
            error_log(
                "Uncaught Exception: " . $exception->getMessage() .
                " in " . $exception->getFile() . ":" . $exception->getLine() .
                "\nStack trace:\n" . $exception->getTraceAsString()
            );
            echo json_encode(['status' => 'error', 'message' => 'An unexpected server error occurred.']);
            exit();
        }
    }

    if (!function_exists('fatal_error_shutdown_handler')) {
        function fatal_error_shutdown_handler() {
            $last_error = error_get_last();
            if ($last_error && in_array($last_error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
                global_exception_handler(new ErrorException($last_error['message'], 0, $last_error['type'], $last_error['file'], $last_error['line']));
            }
        }
    }

    if (!function_exists('error_to_exception_handler')) {
        function error_to_exception_handler($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    }

    set_exception_handler('global_exception_handler');
    set_error_handler('error_to_exception_handler');
    register_shutdown_function('fatal_error_shutdown_handler');

    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}

// --- CORS Handling ---
require_once __DIR__ . '/load_env.php'; // Ensure environment variables are loaded

$allowedOrigin = $_ENV['FRONTEND_PUBLIC_URL'] ?? '';
if (empty($allowedOrigin)) {
    error_log('FRONTEND_PUBLIC_URL is not set in .env. Falling back to * for Access-Control-Allow-Origin.');
    $allowedOrigin = '*';
}
header("Access-Control-Allow-Origin: " . $allowedOrigin);
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Environment & Database ---
global $pdo;
if (!isset($pdo)) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
    define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
    define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? '');
    define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? '');
    define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

    if (!class_exists('PDO')) {
        error_log("Fatal Error: PDO class not found.");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: PDO is not available.']);
        exit();
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
        exit();
    }
}

// --- Session Handling ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
