<?php

// --- Environment Variable Loading ---
function load_env() {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            // Trim whitespace first, then quotes for robustness
            $value = trim(trim($value), '"');
            putenv(trim($name) . '=' . $value);
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
// No JWT configuration needed for session-based auth.
?>