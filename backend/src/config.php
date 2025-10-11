<?php

// --- UNIFIED BOOTSTRAP ---
// This file is now the single source of truth for application initialization.
// It handles error reporting, dependency loading, and configuration for all entry points.

// --- Global Error & Exception Handling ---
ini_set('display_errors', 0); // Do not display errors to the user
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
// Correct the path for Telegram.php to be relative to this file's location.
require_once __DIR__ . '/core/DotEnv.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Telegram.php'; // This was potentially a problem, now fixed.

// --- Environment Variable Loading (CONTEXT-AWARE) ---
// This logic now correctly finds the .env file whether run from web or CLI.
$isCli = (php_sapi_name() === 'cli');
// For CLI, we assume the script is run from the project root ('ss'),
// so the path to `backend` must be included.
// For Web (non-CLI), the web server root is `public_html` which acts as our `backend` directory.
$basePath = $isCli ? __DIR__ . '/../../backend' : dirname(__DIR__);
$dotenvPath = $basePath . '/.env';

if (!file_exists($dotenvPath) || !is_readable($dotenvPath)) {
    $errorMessage = "CRITICAL: .env file could not be found or is not readable at the expected path: {$dotenvPath}";
    error_log($errorMessage);
    // Use die() for CLI for clearer error messages in the terminal
    if ($isCli) {
        die($errorMessage . "\n");
    }
    throw new \RuntimeException($errorMessage);
}

$dotenv = new DotEnv($dotenvPath);
$env = $dotenv->getVariables();

// --- Global Constants Definition ---
define('DB_HOST', $env['DB_HOST'] ?? null);
define('DB_PORT', $env['DB_PORT'] ?? 3306);
define('DB_DATABASE', $env['DB_DATABASE'] ?? null);
define('DB_USER', $env['DB_USER'] ?? null);
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? null);
define('TELEGRAM_BOT_TOKEN', $env['TELEGRAM_BOT_TOKEN'] ?? null);
define('TELEGRAM_WEBHOOK_SECRET', $env['TELEGRAM_WEBHOOK_SECRET'] ?? null);
define('TELEGRAM_CHANNEL_ID', $env['TELEGRAM_CHANNEL_ID'] ?? null);

// --- Global Request Body ---
// This should only run in the context of a web request, not a CLI script.
if (!$isCli) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
    }
}
