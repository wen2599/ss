<?php
// backend/config.php

// Include Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // This error is expected if .env file is not found. 
    // In a production environment, variables should be set directly.
    error_log('.env file not found at path: ' . $e->getPath() . '. This is not an error if environment variables are set on the server.');
}

// --- Environment Variable Validation ---
// On shared hosting, these might be set in the control panel. On a VPS, they are system-wide.
$required_env_vars = [
    'WORKER_SECRET',
    'DB_HOST',
    'DB_USER',
    'DB_PASS',
    'DB_NAME',
    'TELEGRAM_BOT_TOKEN',
    'TELEGRAM_ADMIN_ID',
    'TELEGRAM_CHANNEL_ID',
];

$missing_vars = [];
foreach ($required_env_vars as $var) {
    // Use $_ENV or $_SERVER as getenv() can be unreliable in some PHP setups (FPM/CGI).
    if (!isset($_ENV[$var]) && !isset($_SERVER[$var])) {
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars)) {
    $error_message = 'FATAL: Required environment variables are not set: ' . implode(', ', $missing_vars);
    error_log($error_message);
    http_response_code(500);
    // Avoid exposing variable names in the public error message for security.
    die(json_encode(['error' => 'Server configuration error. Please contact the administrator.']));
}

// --- Security ---
define('WORKER_SECRET', $_ENV['WORKER_SECRET'] ?? $_SERVER['WORKER_SECRET']);

// --- Database Credentials ---
define('DB_HOST', $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST']);
define('DB_USER', $_ENV['DB_USER'] ?? $_SERVER['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS']);
define('DB_NAME', $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME']);

// --- Telegram Bot & Channel ---
define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?? $_SERVER['TELEGRAM_BOT_TOKEN']);
define('TELEGRAM_ADMIN_ID', $_ENV['TELEGRAM_ADMIN_ID'] ?? $_SERVER['TELEGRAM_ADMIN_ID']);
define('TELEGRAM_CHANNEL_ID', $_ENV['TELEGRAM_CHANNEL_ID'] ?? $_SERVER['TELEGRAM_CHANNEL_ID']);


// --- File Uploads ---
define('UPLOADS_DIR', __DIR__ . '/uploads');
?>
