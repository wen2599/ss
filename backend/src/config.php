<?php

// --- Final, CWD-based .env Loader ---

// The setup_database.php script (and eventually our front controller) will set the
// Current Working Directory (CWD) to `backend`. From there, the .env file is one level up.
$envPath = '../.env';

if (!file_exists($envPath)) {
    // This is the definitive check. It tells us the CWD and the path we tried.
    die("FATAL ERROR: .env file not found. CWD: " . getcwd() . ". Attempted path: " . realpath(dirname($envPath)) . DIRECTORY_SEPARATOR . basename($envPath) . "\n");
} else if (!is_readable($envPath)) {
    die("FATAL ERROR: .env file exists but is NOT READABLE. Please check permissions (e.g., chmod 644 .env).\n");
}

// --- Custom .env Loader ---
function loadDotEnv($path)
{
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

// --- Bootstrap ---
loadDotEnv($envPath);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Telegram.php';

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? 'my_database');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

if (php_sapi_name() !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
    }
}
