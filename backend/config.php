<?php
// backend/config.php

// --- Environment Variable Validation ---
$required_env_vars = [
    'WORKER_SECRET',
    'DB_HOST',
    'DB_USER',
    'DB_PASS',
    'DB_NAME',
];

$missing_vars = [];
foreach ($required_env_vars as $var) {
    if (getenv($var) === false) {
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars)) {
    $error_message = 'Fatal Error: Required environment variables are not set: ' . implode(', ', $missing_vars);
    error_log($error_message);
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration error. Please check server logs.']));
}

// --- Security ---
define('WORKER_SECRET', getenv('WORKER_SECRET'));

// --- Database Credentials ---
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));

// --- Telegram Bot & Channel ---
// TELEGRAM_BOT_TOKEN: Your bot's unique token from BotFather.
// TELEGRAM_ADMIN_ID: Your personal Telegram User ID, for admin access control.
// TELEGRAM_CHANNEL_ID: The ID of the channel the bot will read lottery results from (e.g., -1001234567890).
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: null);
define('TELEGRAM_ADMIN_ID', getenv('TELEGRAM_ADMIN_ID') ?: null);
define('TELEGRAM_CHANNEL_ID', getenv('TELEGRAM_CHANNEL_ID') ?: null);


// --- File Uploads ---
define('UPLOADS_DIR', __DIR__ . '/uploads');
?>