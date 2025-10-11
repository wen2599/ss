<?php

// --- Global Error & Exception Handling ---
// Ensures any error is caught and returned as a clean JSON response.
ini_set('display_errors', 0); // Do not display errors to the user
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    // Log the actual error to the server's error log for debugging.
    error_log($exception);

    // Send a generic, clean error response to the client.
    // We require Response.php before this can be used, so we do a check.
    if (class_exists('Response')) {
        Response::json([
            'error' => 'An internal server error occurred.',
            'message' => $exception->getMessage()
        ], 500);
    } else {
        // Fallback if Response class is not available
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Bootstrap failed.']);
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
// Load all essential files in one place.
require_once __DIR__ . '/src/core/Response.php';
require_once __DIR__ . '/src/core/DotEnv.php';
require_once __DIR__ . '/src/core/Database.php';
require_once __DIR__ . '/src/core/Telegram.php';

// --- Environment Variable Loading ---
// Load the .env file from the project's backend root directory.
$env = [];
$dotenvPath = __DIR__ . '/.env';

if (file_exists($dotenvPath) && is_readable($dotenvPath)) {
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
} else {
    // If .env is missing, throw a fatal error. The application cannot run without it.
    throw new \RuntimeException("CRITICAL: .env file could not be found or is not readable at: " . $dotenvPath);
}

// --- Global Constants Definition ---
// Define all configuration constants from the loaded environment variables.
define('DB_HOST', $env['DB_HOST'] ?? null);
define('DB_PORT', $env['DB_PORT'] ?? 3306);
define('DB_DATABASE', $env['DB_DATABASE'] ?? null);
define('DB_USER', $env['DB_USER'] ?? null);
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? null);
define('TELEGRAM_BOT_TOKEN', $env['TELEGRAM_BOT_TOKEN'] ?? null);
define('TELEGRAM_WEBHOOK_SECRET', $env['TELEGRAM_WEBHOOK_SECRET'] ?? null);
define('TELEGRAM_CHANNEL_ID', $env['TELEGRAM_CHANNEL_ID'] ?? null);

// --- Global Request Body ---
// Set global request body for POST/PUT requests for easy access in handlers.
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
}