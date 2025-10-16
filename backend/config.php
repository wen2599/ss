<?php

// --- PHP Error Reporting Configuration (Moved to top) ---
// This MUST be the very first thing to run to ensure any fatal errors are caught.
$log_dir_for_errors = __DIR__ . '/logs';
if (!is_dir($log_dir_for_errors)) {
    // Use @ to suppress errors if the directory can't be created, which itself could be a fatal error.
    @mkdir($log_dir_for_errors, 0775, true);
}
ini_set('display_errors', '0'); // Set to 0 for production to avoid leaking info to the frontend.
ini_set('log_errors', '1');
ini_set('error_log', $log_dir_for_errors . '/php_errors.log');
error_reporting(E_ALL);

// --- Robust, Permission-Safe Logging (Debug Mode) ---
// Logs to the system's temporary directory to bypass any local permission issues.
function write_log($message, $filename = 'app.log') {
    $logFile = sys_get_temp_dir() . '/' . $filename;
    $formatted_message = date('[Y-m-d H:i:s]') . ' [CONFIG] ' . $message . PHP_EOL;
    // Use @ to suppress errors if file_put_contents fails, which can happen in restrictive environments.
    @file_put_contents($logFile, $formatted_message, FILE_APPEND | LOCK_EX);
}

write_log("------ Config.php Entry Point ------", 'config.log');

/**
 * Robust .env loader
 */
function load_env_robust() {
    write_log("Attempting to load .env file.", 'debug_env.log');
    $envPath = __DIR__ . '/.env';
    write_log("Expected .env path: {$envPath}", 'debug_env.log');

    if (!file_exists($envPath)) {
        write_log("Error: .env file does not exist at path.", 'debug_env.log');
        return false;
    }
    write_log("OK: .env file exists.", 'debug_env.log');

    if (!is_readable($envPath)) {
        write_log("Error: .env file is not readable. Check permissions.", 'debug_env.log');
        // Log current user/group to help debug permission issues
        $user = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'N/A';
        write_log("Script running as user: {$user}", 'debug_env.log');
        return false;
    }
    write_log("OK: .env file is readable.", 'debug_env.log');

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        write_log("Error: Failed to read .env file content with file() function.", 'debug_env.log');
        return false;
    }
    write_log("OK: Read " . count($lines) . " lines from .env file.", 'debug_env.log');

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) continue;

        if (strpos($trim, '=') === false) continue;
        list($key, $value) = explode('=', $trim, 2);
        $key = trim($key);
        $value = trim($value);

        if ((substr($value, 0, 1) === '\"' && substr($value, -1) === '\"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        write_log("Loaded env var: {$key}", 'env.log');
    }

    return true;
}

// Load environment variables only once per request.
if (!defined('ENV_LOADED_ROBUST')) {
    write_log("ENV_LOADED_ROBUST not defined, loading env variables.", 'config.log');
    load_env_robust();
    define('ENV_LOADED_ROBUST', true);
    write_log("ENV_LOADED_ROBUST defined after loading.", 'config.log');
    write_log("DB_HOST after load: " . (getenv('DB_HOST') ? 'loaded' : 'N/A'), 'env.log');
}

write_log("PHP error reporting configured.", 'config.log');

// --- Helper Scripts Inclusion ---
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/env_manager.php';

write_log("------ Config.php Exit Point ------", 'config.log');

?>
