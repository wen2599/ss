<?php

// Define a constant for the base directory to ensure consistent paths.
if (!defined('DIR')) {
    define('DIR', __DIR__);
}

// --- Custom Debug Logging Function ---
// This function is kept for debugging purposes but the primary error handling will now be a fatal exit.
function write_custom_debug_log($message) {
    $logFile = DIR . '/env_debug.log';
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_custom_debug_log("------ Config.php Entry Point ------");

/**
 * Robust .env loader
 * This function will now terminate with a clear error message if the .env file is missing.
 */
function load_env_robust() {
    $envPaths = [
        dirname(__DIR__) . '/.env', // Check parent directory (project root)
        __DIR__ . '/.env'           // Check current directory (backend/)
    ];

    $envPathFound = null;

    foreach ($envPaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $envPathFound = $path;
            break;
        }
    }

    // --- CRITICAL ERROR HANDLING ---
    // If no .env file is found after checking all paths, terminate execution.
    if ($envPathFound === null) {
        // Use echo for CLI scripts and error_log for web requests.
        $errorMessage = "FATAL ERROR: Could not find a readable .env file. Please ensure it exists in the project root directory.";
        echo $errorMessage . PHP_EOL;
        error_log($errorMessage, 0);
        exit(1); // Exit with a non-zero status code to indicate failure.
    }

    $lines = file($envPathFound, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        $errorMessage = "FATAL ERROR: Failed to read the .env file at: {$envPathFound}. Check file permissions.";
        echo $errorMessage . PHP_EOL;
        error_log($errorMessage, 0);
        exit(1);
    }

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) {
            continue;
        }

        if (strpos($trim, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $trim, 2);
        $key = trim($key);
        $value = trim($value);

        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    return true;
}

// Load environment variables only once per request.
if (!defined('ENV_LOADED_ROBUST')) {
    load_env_robust();
    define('ENV_LOADED_ROBUST', true);
}

// --- PHP Error Reporting Configuration ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DIR . '/debug.log');
error_reporting(E_ALL);

// --- Helper Scripts Inclusion ---
require_once DIR . '/db_operations.php';
require_once DIR . '/telegram_helpers.php';
require_once DIR . '/user_state_manager.php';
require_once DIR . '/api_curl_helper.php';
require_once DIR . '/gemini_ai_helper.php';
require_once DIR . '/cloudflare_ai_helper.php';
require_once DIR . '/env_manager.php';

write_custom_debug_log("------ Config.php Exit Point ------");

?>
