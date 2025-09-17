<?php
// backend/api/config.php

/**
 * Application Configuration
 *
 * This file loads environment variables from a .env file and defines them as constants.
 */

// 1. Require the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Load the .env file
// It will look for a .env file in the parent directory (backend/)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    // This happens if the .env file is not found.
    // We will handle this gracefully below.
    // In a web context, you might want to return a 503 Service Unavailable error.
    die("Error: Could not find the .env file. Please copy .env.example to .env and fill in your credentials.");
}


// 3. Define a helper function to get required env variables
function get_required_env($key) {
    if (!isset($_ENV[$key])) {
        die("Error: Required environment variable '{$key}' is not set in your .env file.");
    }
    return $_ENV[$key];
}

// 4. Define constants from environment variables
// --- Database Configuration ---
define('DB_HOST', get_required_env('DB_HOST'));
define('DB_NAME', get_required_env('DB_NAME'));
define('DB_USER', get_required_env('DB_USER'));
define('DB_PASS', get_required_env('DB_PASS'));

// --- Telegram Bot Configuration ---
define('TELEGRAM_BOT_TOKEN', get_required_env('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_CHANNEL_ID', get_required_env('TELEGRAM_CHANNEL_ID'));
define('TELEGRAM_SUPER_ADMIN_ID', get_required_env('TELEGRAM_SUPER_ADMIN_ID'));

?>
