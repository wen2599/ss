<?php

// Define a constant for the base directory to ensure consistent paths.
if (!defined('DIR')) {
    define('DIR', __DIR__);
}

// --- Custom Debug Logging Function ---
function write_custom_debug_log($message) {
    // Log file is placed in the same directory as this script.
    $logFile = DIR . '/env_debug.log';
    // Use @ to suppress errors if the directory is not writable.
    @file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_custom_debug_log("------ Config.php Entry Point ------");
write_custom_debug_log("Script running as user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'N/A'));

// --- Pre-emptive Writable Check ---
if (!is_writable(DIR)) {
    write_custom_debug_log("FATAL: Directory " . DIR . " is not writable.");
    // We don't exit here, to allow the rest of the script to try, but we log the critical failure.
} else {
    write_custom_debug_log("OK: Directory " . DIR . " is writable.");
}

/**
 * Robust .env loader
 */
function load_env_robust() {
    $envPath = DIR . '/.env';
    write_custom_debug_log("Attempting to load .env from: {$envPath}");

    if (!file_exists($envPath) || !is_readable($envPath)) {
        write_custom_debug_log(".env not found or not readable at {$envPath}");
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        write_custom_debug_log("Failed to read .env file with file() function.");
        return false;
    }

    write_custom_debug_log("Successfully read " . count($lines) . " lines from .env.");

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

        // Log confirmation, but hide sensitive values.
        $sensitive_keys = ['DB_PASSWORD', 'TELEGRAM_BOT_TOKEN', 'GEMINI_API_KEY', 'CLOUDFLARE_API_TOKEN'];
        $display_value = in_array($key, $sensitive_keys, true) ? '***' : $value;
        write_custom_debug_log("Loaded env: {$key} = {$display_value}");
    }

    return true;
}

// Load environment variables only once per request.
if (!defined('ENV_LOADED_ROBUST')) {
    write_custom_debug_log("ENV_LOADED_ROBUST not defined, loading env variables.");
    if (load_env_robust()) {
        write_custom_debug_log("load_env_robust() completed successfully.");
    } else {
        write_custom_debug_log("load_env_robust() failed.");
    }
    define('ENV_LOADED_ROBUST', true);
    write_custom_debug_log("DB_HOST after load: " . (getenv('DB_HOST') ?: 'N/A'));
    write_custom_debug_log("DB_USER after load: " . (getenv('DB_USER') ?: 'N/A'));
}

// --- PHP Error Reporting Configuration ---
ini_set('display_errors', '0'); // Do not display errors to the user in production.
ini_set('log_errors', '1');
ini_set('error_log', DIR . '/debug.log');
error_reporting(E_ALL);
write_custom_debug_log("PHP error reporting configured to log to " . DIR . '/debug.log');

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
