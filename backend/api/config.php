<?php
// backend/api/config.php

/**
 * Application Configuration
 *
 * This file loads environment variables from a .env file located in the parent
 * directory (`backend/`) and defines them as constants.
 * It uses a custom, dependency-free loader.
 */

// Use the custom .env loader instead of Composer/phpdotenv
require_once __DIR__ . '/env_loader.php';

try {
    // The .env file is expected to be in the `backend/` directory,
    // which is one level up from the current `api/` directory.
    $dotenv_path = __DIR__ . '/../.env';
    load_env($dotenv_path);
} catch (Exception $e) {
    http_response_code(503); // Service Unavailable
    // Output a user-friendly error message. The specific error is in the exception.
    die("FATAL ERROR: Could not load the environment configuration. Ensure the .env file exists in the `backend/` directory and is readable. Details: " . $e->getMessage());
}

// Helper function to get required env variables and ensure they are not empty.
function get_required_env($key) {
    if (empty($_ENV[$key])) {
        http_response_code(503); // Service Unavailable
        die("FATAL ERROR: Required environment variable '{$key}' is not set or is empty in your .env file.");
    }
    return $_ENV[$key];
}

// --- Define constants from environment variables ---

// Database Configuration
define('DB_HOST', get_required_env('DB_HOST'));
define('DB_NAME', get_required_env('DB_NAME'));
define('DB_USER', get_required_env('DB_USER'));
define('DB_PASS', get_required_env('DB_PASS'));

// Telegram Bot Configuration
define('TELEGRAM_BOT_TOKEN', get_required_env('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_CHANNEL_ID', get_required_env('TELEGRAM_CHANNEL_ID'));
define('TELEGRAM_SUPER_ADMIN_ID', get_required_env('TELEGRAM_SUPER_ADMIN_ID'));

// Note: The closing PHP tag is intentionally omitted from this file.
// This is a best practice in PHP for files that contain only PHP code.
// It prevents accidental whitespace from being sent to the browser, which
// can cause "headers already sent" errors, especially with session handling.
