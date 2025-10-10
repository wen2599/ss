<?php

// Global Configuration File

require_once __DIR__ . '/core/DotEnv.php';

// --- Load Environment Variables ---
// This is the definitive, robust path fix. It uses this file's location
// to reliably calculate the project root, which is two directories up.
// This avoids all issues with relative paths (../), symlinks, and
// differences in server environments (e.g., Apache vs. PHP-S).
$env = [];
$dotenvPath = dirname(__DIR__, 2) . '/.env';

// --- Diagnostic Logging ---
error_log("Config: Checking for .env file at path: " . $dotenvPath);

if (file_exists($dotenvPath)) {
    error_log("Config: .env file found. Loading variables.");
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
} else {
    error_log("Config: .env file NOT found at path: " . $dotenvPath);
    // If the file is not found, we throw a fatal exception because
    // the application cannot function without its configuration.
    throw new \RuntimeException("CRITICAL: .env file not found at expected absolute path: {$dotenvPath}.");
}

// --- Error Reporting (for development) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Database Configuration ---
define('DB_HOST', $env['DB_HOST'] ?? null);
define('DB_PORT', $env['DB_PORT'] ?? 3306);
define('DB_DATABASE', $env['DB_DATABASE'] ?? null);
define('DB_USER', $env['DB_USER'] ?? null);
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? null);

// --- Telegram Configuration ---
define('TELEGRAM_BOT_TOKEN', $env['TELEGRAM_BOT_TOKEN'] ?? null);
define('TELEGRAM_WEBHOOK_SECRET', $env['TELEGRAM_WEBHOOK_SECRET'] ?? null);
define('TELEGRAM_CHANNEL_ID', $env['TELEGRAM_CHANNEL_ID'] ?? null);