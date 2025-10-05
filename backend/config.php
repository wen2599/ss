<?php
// backend/config.php

// --- Environment Variable Validation ---
// Ensure core environment variables are set. Specific components validate their own variables.
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
        // The .env file is missing or the server environment is not configured.
        $missing_vars[] = $var;
    }
}

if (!empty($missing_vars)) {
    $error_message = 'Fatal Error: Required environment variables are not set: ' . implode(', ', $missing_vars);
    // Log the error to the standard PHP error log.
    error_log($error_message);
    // Set a 500 Internal Server Error status code.
    http_response_code(500);
    // Output a generic error message to the client.
    // This prevents leaking sensitive configuration details.
    die(json_encode(['error' => 'Server configuration error. Please check server logs.']));
}

// --- Security ---
// The secret for communicating with the Cloudflare Worker.
define('WORKER_SECRET', getenv('WORKER_SECRET'));

// --- Database Credentials ---
// These are loaded from the .env file via bootstrap.php or from the server environment.
define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));

// --- Telegram Bot ---
// Credentials for the administration bot. These are validated within the Telegram webhook endpoint.
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: null);
define('TELEGRAM_ADMIN_ID', getenv('TELEGRAM_ADMIN_ID') ?: null);

// --- File Uploads ---
// The directory where email files and attachments will be stored.
define('UPLOADS_DIR', __DIR__ . '/uploads');
?>