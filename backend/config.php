<?php
/**
 * config.php
 *
 * Centralized configuration and bootstrap file.
 * This is the SINGLE entry point for all application-wide settings and helpers.
 * Any script that needs access to the database, Telegram functions, etc., should
 * start with `require_once __DIR__ . '/config.php';`.
 */

// --- Environment Variable Loading ---
if (!function_exists('load_environment_variables')) {
    function load_environment_variables($path) {
        if (!file_exists($path) || !is_readable($path)) {
            error_log("FATAL: Environment file not found or not readable at {$path}");
            return false;
        }
        if (getenv('DB_HOST')) return true; // Prevent re-loading
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            error_log("FATAL: Could not read environment file at {$path}");
            return false;
        }
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || strpos($trim, '#') === 0) continue;
            if (strpos($trim, '=') !== false) {
                list($key, $value) = explode('=', $trim, 2);
                $key = trim($key);
                $value = trim($value, "\"'");
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        return true;
    }
}

$env_path = __DIR__ . '/.env';
if (!load_environment_variables($env_path)) {
    http_response_code(500);
    error_log("CRITICAL: .env file could not be loaded from {$env_path}. Application cannot run.");
    exit('Internal Server Error: Missing environment configuration.');
}

// --- Error Handling & Logging ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
$log_file = __DIR__ . '/debug.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);
date_default_timezone_set('UTC');

// --- Global Helper Inclusion ---
// Include all shared function files here so any script including config.php
// has access to them. This is the core of the fix.
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/env_manager.php';
require_once __DIR__ . '/lottery_parser.php';

?>