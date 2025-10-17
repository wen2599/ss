<?php

// Define a constant for the base directory to ensure consistent paths.
if (!defined('DIR')) {
    define('DIR', __DIR__);
}

// --- PHP Error Reporting Configuration ---
ini_set('display_errors', '0'); // Do not display errors to the user in production.
ini_set('log_errors', '1');
ini_set('error_log', DIR . '/debug.log'); // Log errors to a file.
error_reporting(E_ALL);

/**
 * Loads environment variables from a .env file.
 * This function is called only once per request.
 */
function load_env() {
    // Check if already loaded to prevent redundant work.
    if (defined('ENV_LOADED')) {
        return;
    }

    $envPath = DIR . '/.env';
    if (!file_exists($envPath) || !is_readable($envPath)) {
        // If the .env file is missing, the application cannot function.
        // We log this critical error. The subsequent DB connection will fail.
        error_log("CRITICAL: .env file not found or not readable at {$envPath}");
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        error_log("CRITICAL: Failed to read .env file at {$envPath}");
        return;
    }

    foreach ($lines as $line) {
        // Skip comments and invalid lines
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes from the value
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Set environment variables
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    // Mark as loaded
    define('ENV_LOADED', true);
}

// Execute the environment loader.
load_env();

// --- Helper Scripts Inclusion ---
// These scripts rely on the environment variables loaded above.
require_once DIR . '/db_operations.php';
require_once DIR . '/telegram_helpers.php';
require_once DIR . '/user_state_manager.php';
require_once DIR . '/api_curl_helper.php';
require_once DIR . '/gemini_ai_helper.php';
require_once DIR . '/cloudflare_ai_helper.php';
require_once DIR . '/env_manager.php';

?>