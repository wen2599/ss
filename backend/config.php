<?php

// Define a constant for the base directory to ensure consistent paths.
if (!defined('DIR')) {
    define('DIR', __DIR__);
}

// --- Custom Debug Logging Function (now a wrapper for error_log) ---
function write_custom_debug_log($message) {
    // Prepends a tag to distinguish config-related logs.
    error_log('[CONFIG] ' . $message);
}

// --- PHP Error Reporting Configuration (SINGLE SOURCE OF TRUTH) ---
// This block should be at the very top to ensure all subsequent errors are caught.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DIR . '/debug.log');
error_reporting(E_ALL);

write_custom_debug_log("------ Config.php Entry Point ------");
write_custom_debug_log("PHP error reporting configured to log to " . DIR . '/debug.log');

/**
 * Robust .env loader
 */
function load_env_robust() {
    // The .env file is in the project root, one level up from the /backend directory.
    $envPath = DIR . '/../.env';

    if (!file_exists($envPath) || !is_readable($envPath)) {
        write_custom_debug_log(".env file not found or not readable at {$envPath}");
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

        // Remove surrounding quotes from value
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        // Log confirmation, but hide sensitive values.
        $sensitive_keys = ['DB_PASSWORD', 'TELEGRAM_BOT_TOKEN', 'GEMINI_API_KEY', 'CLOUDFLARE_API_TOKEN', 'TELEGRAM_WEBHOOK_SECRET'];
        $display_value = in_array($key, $sensitive_keys, true) ? '***' : $value;
        write_custom_debug_log("Loaded env: {$key} = {$display_value}");
    }

    return true;
}

// Load environment variables only once per request.
if (!defined('ENV_LOADED_ROBUST')) {
    write_custom_debug_log("ENV_LOADED_ROBUST not defined, proceeding to load.");
    if (load_env_robust()) {
        write_custom_debug_log("load_env_robust() completed successfully.");
    } else {
        write_custom_debug_log("load_env_robust() failed. Critical environment variables may be missing.");
    }
    define('ENV_LOADED_ROBUST', true);
}

// --- Pre-emptive Writable Check ---
// Check this after setting up logging, so the error is sure to be captured.
if (!is_writable(DIR)) {
    write_custom_debug_log("FATAL: Main directory " . DIR . " is not writable. Log files cannot be created.");
}

// --- Helper Scripts Inclusion ---
require_once DIR . '/db_operations.php';
require_once DIR . '/telegram_helpers.php';
require_once DIR . '/user_state_manager.php';
require_once DIR . '/api_curl_helper.php';
require_once DIR . '/gemini_ai_helper.php';
require_once DIR . '/cloudflare_ai_helper.php';
// env_manager.php is no longer needed as its functionality is in this file.

write_custom_debug_log("------ Config.php Exit Point ------");

?>
