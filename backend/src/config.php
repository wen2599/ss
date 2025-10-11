<?php

// --- UNIFIED BOOTSTRAP ---
// This file is now the single source of truth for application initialization.
// It handles error reporting, dependency loading, and configuration.

// --- Global Error & Exception Handling ---
ini_set('display_errors', 0); // Do not display errors to the user
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    error_log($exception);
    if (class_exists('Response')) {
        Response::json(['error' => 'An internal server error occurred.'], 500);
    } else {
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
require_once __DIR__ . '/core/DotEnv.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Telegram.php';

// --- Environment Variable Loading ---
// Load the .env file from the project's backend root directory.
$env = [];
$dotenvPath = dirname(__DIR__) . '/.env';

if (file_exists($dotenvPath) && is_readable($dotenvPath)) {
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
} else {
    throw new \RuntimeException("CRITICAL: .env file could not be found or is not readable at: " . $dotenvPath);
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
}