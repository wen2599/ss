<?php

// --- Custom Debug Logging Function ---
function write_custom_debug_log($message) {
    $logFile = __DIR__ . '/env_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_custom_debug_log("------ Config.php Entry Point ------");

// NOTE: The pre-emptive writable check has been removed as it causes issues in the IDX environment.

/**
 * Robust .env loader:
 * - Reads project_root/.env
 * - Parses KEY=VALUE (ignores comments and blank lines)
 * - Calls putenv(), sets $_ENV and $_SERVER
 */
function load_env_robust() {
    $envPath = __DIR__ . '/../.env'; // Correctly point to the root directory's .env file
    if (!file_exists($envPath) || !is_readable($envPath)) {
        write_custom_debug_log("load_env_robust: .env not found or not readable at {$envPath}");
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        write_custom_debug_log("load_env_robust: Failed to read .env file.");
        return false;
    }

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) continue;

        // Support KEY="value with = and spaces" or KEY=value
        if (strpos($trim, '=') === false) continue;
        list($key, $value) = explode('=', $trim, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove optional surrounding quotes for the value
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        // Export to environment and PHP superglobals
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        write_custom_debug_log("Loaded env: {$key} = " . (strlen($value) ? '***' : '(empty)'));
    }

    return true;
}

// Load environment variables only once per request.
if (!defined('ENV_LOADED_ROBUST')) {
    write_custom_debug_log("Config.php: ENV_LOADED_ROBUST not defined, loading env variables.");
    load_env_robust();
    define('ENV_LOADED_ROBUST', true);
    write_custom_debug_log("Config.php: ENV_LOADED_ROBUST defined after loading.");
    write_custom_debug_log("Config.php: DB_HOST after load: " . (getenv('DB_HOST') ?: 'N/A'));
    write_custom_debug_log("Config.php: DB_USER after load: " . (getenv('DB_USER') ?: 'N/A'));
    write_custom_debug_log("Config.php: TELEGRAM_ADMIN_ID after load: " . (getenv('TELEGRAM_ADMIN_ID') ?: 'N/A'));
}

// --- PHP Error Reporting Configuration (for debugging) ---
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);
write_custom_debug_log("Config.php: PHP error reporting configured.");

// --- Helper Scripts Inclusion ---
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/env_manager.php';

write_custom_debug_log("------ Config.php Exit Point ------");

?>
