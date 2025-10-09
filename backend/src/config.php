<?php

// Global Configuration File

require_once __DIR__ . '/core/DotEnv.php';

// --- Load Environment Variables ---
// This is the corrected path to find the .env file in the project root.
$env = [];
$dotenvPath = dirname(__DIR__, 2) . '/.env';

if (file_exists($dotenvPath)) {
    // Use the getVariables method to robustly load credentials
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
} else {
    // Fallback or error if .env is missing.
    // This will now correctly report if the file is not found at the root.
    error_log("CRITICAL: .env file not found at the project root: {$dotenvPath}.");
    // We should exit here to prevent further errors
    // For now, we let it continue so constants are defined, even if empty.
}

// --- Error Reporting ---
// Set to 1 for development, 0 for production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Database Configuration ---
// Use the $env array which is more reliable than $_ENV
define('DB_HOST', $env['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $env['DB_PORT'] ?? '3306');
define('DB_DATABASE', $env['DB_DATABASE'] ?? '');
define('DB_USER', $env['DB_USER'] ?? '');
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? '');

// --- Telegram Configuration ---
define('TELEGRAM_BOT_TOKEN', $env['TELEGRAM_BOT_TOKEN'] ?? '');
define('TELEGRAM_WEBHOOK_SECRET', $env['TELEGRAM_WEBHOOK_SECRET'] ?? '');
define('TELEGRAM_CHANNEL_ID', $env['TELEGRAM_CHANNEL_ID'] ?? '');

// --- CORS (Cross-Origin Resource Sharing) Headers ---
// This is now handled by the Cloudflare Worker (_worker.js), but kept here for potential direct API access
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: *"); // Consider restricting this in production
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}