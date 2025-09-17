<?php
// backend/api/config.php

/**
 * Application Configuration
 *
 * This file loads environment variables from a .env file located in the parent
 * directory (`backend/`) and defines them as constants. It also includes the
 * Composer autoloader, which is required for the application to function.
 */

// Require the Composer autoloader
// This makes the phpdotenv library available.
require_once __DIR__ . '/../vendor/autoload.php';

// Load the .env file from the parent directory (`backend/`)
try {
    // Note the path: __DIR__ . '/../' points to the `backend` directory
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // This happens if the .env file is not found.
    http_response_code(503); // Service Unavailable
    die("FATAL ERROR: Could not find the .env file. Please copy .env.example to .env in the `backend/` directory and fill in your credentials.");
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

?>
