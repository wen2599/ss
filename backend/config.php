<?php

// --- Environment Variable Loading ---
function load_env() {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $value = trim($value, '"');
            putenv(trim($name) . '=' . $value);
        }
    }
}
load_env();

// --- Helper Scripts Inclusion ---
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php'; // Load the generic cURL helper first
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';
require_once __DIR__ . '/env_manager.php';

// --- JWT Configuration ---
define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'your-super-secret-and-long-key-that-no-one-knows');
define('JWT_TOKEN_LIFETIME', getenv('JWT_TOKEN_LIFETIME') ?: 86400);