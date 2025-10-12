<?php

// --- Final Diagnostic Check ---
$envPath = __DIR__ . '/../../.env';

if (!file_exists($envPath)) {
    die("FATAL ERROR: The .env file was not found at the expected path: " . realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . ".env\n");
} else if (!is_readable($envPath)) {
    die("FATAL ERROR: The .env file was found, but it is NOT READABLE by the PHP process. Please check file permissions (e.g., chmod 644 .env).\n");
}

// --- Custom .env Loader ---
function loadDotEnv($path)
{
    // We already confirmed the file exists and is readable, so just load it.
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// --- UNIFIED BOOTSTRAP ---
loadDotEnv($envPath);

// --- Session Start ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Global Error & Exception Handling ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    error_log("Unhandled Exception: " . $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'An internal server error occurred.']);
    }
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Core File Includes ---
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Telegram.php';

// --- Global Constants Definition ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? 'my_database');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// --- Global Request Body ---
if (php_sapi_name() !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
    }
}
