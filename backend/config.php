<?php
// backend/config.php

// Environment variables are loaded by bootstrap.php via Dotenv.
// This script defines constants from those environment variables.

// --- Environment Variable Validation ---
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
    // Use getenv() which is populated by Dotenv in bootstrap.php
    if (getenv($var) === false) {
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
define('WORKER_SECRET', getenv('WORKER_SECRET'));

// --- Database Credentials ---
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));

// --- Telegram Bot & Channel ---
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_ADMIN_ID', getenv('TELEGRAM_ADMIN_ID'));
define('TELEGRAM_CHANNEL_ID', getenv('TELEGRAM_CHANNEL_ID'));


// --- File Uploads ---
define('UPLOADS_DIR', __DIR__ . '/uploads');
?>