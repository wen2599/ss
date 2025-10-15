<?php

// --- Environment Variable Loading ---
// This function loads variables from .env into the $_ENV and $_SERVER superglobals.
// This is a safer and more compatible method than using putenv().
function load_env() {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        // Trim whitespace first, then quotes for robustness
        $value = trim(trim($value), '"');

        // Populate the superglobals
        if (!array_key_exists($name, $_SERVER)) {
            $_SERVER[$name] = $value;
        }
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}
load_env();


// --- Pre-emptive Writable Check ---
// This check remains as a safeguard against permission issues.
if (!is_writable(__DIR__)) {
    $adminChatId = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? null;
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;

    if ($adminChatId && $botToken) {
        $errorMessage = "CRITICAL ERROR: The bot's directory on the server is not writable. The bot cannot function. Please correct the file permissions.";
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $payload = json_encode(['chat_id' => $adminChatId, 'text' => $errorMessage]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }

    http_response_code(500);
    exit("FATAL: Directory not writable.");
}


// --- PHP Error Reporting Configuration ---
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);


// --- Helper Scripts Inclusion ---
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/env_manager.php';

?>