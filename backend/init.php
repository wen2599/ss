<?php
// --- Custom Session Handling for Shared Hosting ---
// Define a dedicated, writable directory for session files within our project.
$session_path = __DIR__ . '/sessions';

// Ensure the directory exists. This is a safeguard.
if (!is_dir($session_path)) {
    // Attempt to create it if it doesn't exist.
    // The @ suppresses errors if the directory already exists from a race condition.
    @mkdir($session_path, 0755, true);
}

// Set the session save path *before* starting the session.
// This is the crucial step to fix the 502 error on the server.
session_save_path($session_path);


// Start session
if (session_status() == PHP_SESSION_NONE) {
    // Now, session_start() will use the reliable path we just defined.
    session_start();
}

// Set CORS headers
header("Access-Control-Allow-Origin: *"); // In production, restrict this to your frontend domain
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Worker-Secret");

// Handle OPTIONS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Robust Error and Exception Handling ---

// JSON response helper function that prevents errors if headers are already sent
function json_response($data, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

// Set a global exception handler that will catch any uncaught exceptions
set_exception_handler(function(Throwable $e) {
    // In a real app, you would log the error: error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    json_response(['error' => 'An internal server error occurred. Please contact support.'], 500);
});

// Set an error handler to convert all errors (warnings, notices, etc.) to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Ensure errors are not displayed to the user, as we handle them ourselves.
ini_set('display_errors', '0');
error_reporting(E_ALL);


// --- Configuration Loading ---

// Load environment variables from .env file
function load_env($path) {
    if (!file_exists($path)) {
        throw new Exception(".env file not found at " . $path);
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

load_env(__DIR__ . '/.env');


// --- Database Connection ---

$pdo = null;
try {
    $host = $_ENV['DB_HOST'] ?? null;
    $dbname = $_ENV['DB_NAME'] ?? null;
    $user = $_ENV['DB_USER'] ?? null;
    $pass = $_ENV['DB_PASS'] ?? ''; // Default to empty string if not set

    if (!$host || !$dbname || !$user) {
        throw new Exception("Database configuration is incomplete in the .env file.");
    }

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    // Re-throw the exception to be caught by our global exception handler,
    // which will log it and return a generic JSON error message.
    throw $e;
}
?>