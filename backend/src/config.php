<?php

// --- Custom .env Loader ---
function loadDotEnv($path)
{
    if (!file_exists($path)) {
        // Silently fail if the .env file doesn't exist.
        // This allows for environments where config is set via server, not a file.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove quotes from the value
        if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
            $value = substr($value, 1, -1);
        }

        // Set as environment variables
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// --- UNIFIED BOOTSTRAP ---
// Load the .env file from the parent directory (which is the project root).
// This path assumes `config.php` is in `backend/src`, and `.env` is at the root.
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
// The getenv() function fetches the values loaded from your .env file.
// The null coalescing operator (??) provides a default value if the env var is not set.
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
