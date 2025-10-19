<?php

if (!defined('DIR')) {
    define('DIR', __DIR__);
}

// --- PHP Error Reporting Configuration ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DIR . '/debug.log');
error_reporting(E_ALL);

function write_log($message) {
    // Ensure message is a string
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    // Prepend timestamp and append newline
    $formatted_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    // Use error_log to write to the configured log file
    error_log($formatted_message);
}

write_log("------ Config.php Entry Point ------");

/**
 * Robust .env loader
 */
function load_env_robust() {
    $envPath = DIR . '/.env';

    if (!file_exists($envPath) || !is_readable($envPath)) {
        write_log("FATAL: .env file not found or not readable at {$envPath}");
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        write_log("Failed to read .env file with file() function from: {$envPath}");
        return false;
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
    write_log("ENV_LOADED_ROBUST not defined, loading env variables.");
    if (load_env_robust()) {
        write_log("load_env_robust() completed successfully.");
    } else {
        write_log("load_env_robust() failed.");
    }
    define('ENV_LOADED_ROBUST', true);
}

// --- Helper Scripts Inclusion ---
require_once DIR . '/db_operations.php';
require_once DIR . '/telegram_helpers.php';
require_once DIR . '/user_state_manager.php';
require_once DIR . '/api_curl_helper.php';
require_once DIR . '/gemini_ai_helper.php';
require_once DIR . '/cloudflare_ai_helper.php';
require_once DIR . '/env_manager.php';

write_log("------ Config.php Exit Point ------");

?>
