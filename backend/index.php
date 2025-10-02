<?php

// index.php
// This file is the central router for all API requests.

// Use the centralized initialization script
require_once __DIR__ . '/init.php';

// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Note: Dotenv is not needed here anymore if config.php handles it.
// We will assume config.php, included by init.php, manages environment variables.

// --- All the setup below is now handled by init.php ---
// - Error reporting
// - Session start
// - CORS headers
// - Content-Type header
// - Database connection ($pdo)
// - Global exception handler
// ----------------------------------------------------

// 1. Logger Setup (can be kept here or moved to init.php if preferred)
// For now, let's keep it here to allow route-specific logging setup.
$logLevel = Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO');
$log = new Logger('app');
$log->pushHandler(new StreamHandler(__DIR__ . '/app.log', $logLevel));

$log->info("--- New Request: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown URI') . " ---");


// 2. Security Check: Validate the secret header from the Cloudflare Worker.
// This is specific to the entry point, so it stays here.
$workerSecret = $_ENV['WORKER_SECRET'] ?? '';
if (!$workerSecret || !isset($_SERVER['HTTP_X_WORKER_SECRET']) || $_SERVER['HTTP_X_WORKER_SECRET'] !== $workerSecret) {
    $log->warning("Forbidden access attempt. Worker secret missing or invalid.");
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: Missing or invalid secret.']);
    exit();
}

// 3. Routing with Whitelist
$action = $_GET['action'] ?? '';

// Define a whitelist of allowed actions
$allowedActions = [
    'check_session',
    'delete_bill',
    'email_upload',
    'get_bills',
    'get_game_data',
    'get_lottery_results',
    'is_user_registered',
    'login',
    'logout',
    'process_text',
    'register',
    'update_settlement',
];

if ($action && in_array($action, $allowedActions)) {
    $action_file = __DIR__ . '/actions/' . $action . '.php';
    if (file_exists($action_file)) {
        // The action file will have its own error handling, but the global
        // handler in init.php will catch anything it doesn't.
        require $action_file;
    } else {
        $log->error("Action file for '{$action}' not found despite being in whitelist.");
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error: Action file missing.']);
    }
} else {
    $log->warning("Attempted to access unknown or disallowed action: '{$action}'");
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Endpoint not found.']);
}

?>
