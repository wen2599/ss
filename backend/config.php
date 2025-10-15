<?php

// Unconditional first-line test to see if the script executes and can write.
file_put_contents(__DIR__ . '/execution_start.log', 'Config file was executed at ' . date('Y-m-d H:i:s'));

// --- Pre-emptive Writable Check ---
// This runs before anything else. If the directory isn't writable,
// we send a direct, hardcoded error message to the admin and die.
// This prevents silent failures if logging or state management fails.
if (!is_writable(__DIR__)) {
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
function load_env() {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove surrounding quotes (single or double) from the value
            if (strlen($value) > 1 && ($value[0] === '"' && $value[strlen($value) - 1] === '"')) {
                $value = substr($value, 1, -1);
            } elseif (strlen($value) > 1 && ($value[0] === "'" && $value[strlen($value) - 1] === "'")) {
                $value = substr($value, 1, -1);
            }

            // Set environment variable for the current script
            $_ENV[$name] = $value;
            // Set environment variable for processes spawned by the script
            putenv("$name=$value");
        }
    }
}
load_env();

// --- PHP Error Reporting Configuration (for debugging) ---
ini_set('display_errors', '1'); // Temporarily display errors
ini_set('log_errors', '1');     // Log errors
ini_set('error_log', __DIR__ . '/debug.log'); // Direct errors to a specific file
error_reporting(E_ALL); // Report all errors


// --- Helper Scripts Inclusion ---
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php'; // Shared cURL function
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/env_manager.php';
// No JWT configuration needed for session-based auth.
?>