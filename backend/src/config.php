<?php

// Global Configuration File

require_once __DIR__ . '/core/DotEnv.php';

// --- Load Environment Variables ---
// Load .env file from the root of the 'backend' directory
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    (new DotEnv($dotenvPath))->load();
} else {
    // Fallback or error if .env is missing in a production environment
    // For development, we can allow it to fail silently, but in production, you might want to die().
    // error_log("Warning: .env file not found. Using environment variables or defaults.");
}

// --- Error Reporting ---
// Set to 1 for development, 0 for production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Database Configuration ---
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? '');
define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? '');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// --- Telegram Configuration ---
define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
define('TELEGRAM_WEBHOOK_SECRET', $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '');
define('TELEGRAM_CHANNEL_ID', $_ENV['TELEGRAM_CHANNEL_ID'] ?? '');

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
