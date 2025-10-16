<?php

// --- Custom Debug Logging Function ---
// This function attempts to write to a specific log file, bypassing standard error_log if needed.
function write_custom_debug_log($message) {
    $logFile = __DIR__ . '/env_debug.log';
    // Append message with timestamp and newline
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_custom_debug_log("------ Config.php Entry Point ------");

// --- Pre-emptive Writable Check ---
// This runs before anything else. If the directory isn't writable,
// we send a direct, hardcoded error message to the admin and die.
// This prevents silent failures if logging or state management fails.
if (!is_writable(__DIR__)) {
    // Only attempt to send Telegram message if essential config exists
    $adminChatId = getenv('TELEGRAM_ADMIN_CHAT_ID') ?: 'YOUR_ADMIN_CHAT_ID'; // Failsafe
    $botToken = getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN'; // Failsafe

    if ($adminChatId && $botToken && $adminChatId !== 'YOUR_ADMIN_CHAT_ID') {
        $errorMessage = "CRITICAL ERROR: The bot's directory on the server is not writable. The bot cannot function. Please correct the file permissions.";
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $payload = json_encode(['chat_id' => $adminChatId, 'text' => $errorMessage]);

        // Use a basic cURL to send the error, avoiding all other dependencies.
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5-second timeout
        curl_exec($ch);
        curl_close($ch);
    }

    // Stop all further execution.
    http_response_code(500);
    exit("FATAL: Directory not writable.");
}

// --- Environment Variable Loading ---
// This robust loader ensures variables are available across different server configurations (SAPIs).
function load_env_robust() {
    $envPath = __DIR__ . '/.env';
    write_custom_debug_log("load_env_robust: Attempting to load .env from: {$envPath}");

    if (file_exists($envPath)) {
        write_custom_debug_log("load_env_robust: .env file found.");
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim(trim($value), '"'); // Trim whitespace and quotes

            // Populate all common places environment variables are stored
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }
            putenv("{$name}={$value}");
            write_custom_debug_log("load_env_robust: Set env variable: {$name}");
        }
        write_custom_debug_log("load_env_robust: Finished processing .env file.");
    } else {
        write_custom_debug_log("load_env_robust: .env file NOT found at: {$envPath}");
    }
}

// Load environment variables only once per request.
if (!defined('ENV_LOADED_ROBUST')) {
    write_custom_debug_log("Config.php: ENV_LOADED_ROBUST not defined, loading env variables.");
    load_env_robust();
    define('ENV_LOADED_ROBUST', true);
    write_custom_debug_log("Config.php: ENV_LOADED_ROBUST defined after loading.");

    // After loading, check some key variables directly
    write_custom_debug_log("Config.php: DB_HOST after load: " . (getenv('DB_HOST') ?: 'N/A'));
    write_custom_debug_log("Config.php: DB_USER after load: " . (getenv('DB_USER') ?: 'N/A'));
    write_custom_debug_log("Config.php: TELEGRAM_ADMIN_ID after load: " . (getenv('TELEGRAM_ADMIN_ID') ?: 'N/A'));
}

// --- PHP Error Reporting Configuration (for debugging) ---
ini_set('display_errors', '1'); // Temporarily display errors
ini_set('log_errors', '1');     // Log errors
ini_set('error_log', __DIR__ . '/debug.log'); // Direct errors to a specific file
error_reporting(E_ALL); // Report all errors
write_custom_debug_log("Config.php: PHP error reporting configured.");


// --- Helper Scripts Inclusion ---
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php'; // Shared cURL function
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/env_manager.php';
// No JWT configuration needed for session-based auth.

write_custom_debug_log("------ Config.php Exit Point ------");

?>