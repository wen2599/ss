<?php

// --- EXTREME TEMPORARY DEBUGGING BLOCK START (config.php) ---
// This block is to check if config.php can execute AT ALL and output to browser.

echo "Hello from config.php - This is a very early test.";
exit; // Force script to exit immediately after outputting this message.

// --- EXTREME TEMPORARY DEBUGGING BLOCK END ---

// Original config.php content (commented out for this test)
/*

// --- Custom Debug Logging Function ---
function write_custom_debug_log($message) {
    $logFile = __DIR__ . '/env_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_custom_debug_log("------ Config.php Entry Point ------");

// --- Pre-emptive Writable Check ---
if (!is_writable(__DIR__)) {
    // ... (error handling for unwritable directory) ...
    http_response_code(500);
    exit("FATAL: Directory not writable.");
}

// --- Environment Variable Loading ---
function load_env_robust() {
    // ... (env loading logic) ...
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

*/

?>