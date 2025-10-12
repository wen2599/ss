<?php

// --- Custom .env Loader ---
function loadDotEnv($path)
{
    // --- DEBUGGING STEP ---
    // We will stop the script here to show the exact path being checked for the .env file.
    // Compare this path with where you have placed your .env file.
    die("DEBUG: Attempting to load .env file from the following absolute path: " . realpath(dirname($path)) . DIRECTORY_SEPARATOR . basename($path) . "\n");
    // --- END DEBUGGING STEP ---

    if (!file_exists($path)) {
        // Silently fail if the .env file doesn't exist.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // ... (rest of the function is unchanged)
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
            $value = substr($value, 1, -1);
        }

        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// --- UNIFIED BOOTSTRAP ---
// This path assumes `config.php` is in `backend/src`, and `.env` is at the project root.
loadDotEnv(__DIR__ . '/../../.env');

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
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Core File Includes ---
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Telegram.php';

// --- Global Constants Definition (from Environment Variables) ---
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'my_database');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');

// --- Global Request Body ---
if (php_sapi_name() !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
    }
}
