<?php

// --- Robust, Permission-Safe Logging ---
function write_log($message, $filename = 'app.log') {
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/' . $filename;

    // Ensure the directory exists. Suppress errors in case of permission issues.
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    // Prepare the message.
    $formatted_message = date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL;

    // Write to the log file. Suppress errors if writing fails.
    @file_put_contents($logFile, $formatted_message, FILE_APPEND | LOCK_EX);
}

write_log("------ Config.php Entry Point ------", 'config.log');


/**
 * Robust .env loader:
 * - Reads project_root/.env
 * - Parses KEY=VALUE (ignores comments and blank lines)
 * - Calls putenv(), sets $_ENV and $_SERVER
 */
function load_env_robust() {
    $envPath = __DIR__ . '/../.env'; // Correctly point to the root directory's .env file
    if (!file_exists($envPath) || !is_readable($envPath)) {
        write_log("load_env_robust: .env not found or not readable at {$envPath}", 'env.log');
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        write_log("load_env_robust: Failed to read .env file.", 'env.log');
        return false;
    }

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

        // Do not log the value itself for security.
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

// --- PHP Error Reporting Configuration (for debugging) ---
// Note: This will also log to the /logs directory.
$log_dir_for_errors = __DIR__ . '/logs';
if (!is_dir($log_dir_for_errors)) {
    @mkdir($log_dir_for_errors, 0775, true);
}
ini_set('display_errors', '1'); // Should be '0' in production
ini_set('log_errors', '1');
ini_set('error_log', $log_dir_for_errors . '/php_errors.log');
error_reporting(E_ALL);

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
