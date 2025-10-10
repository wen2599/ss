<?php

// Global Configuration File

require_once __DIR__ . '/core/DotEnv.php';

// --- Load Environment Variables ---
// This is the definitive, robust path fix. It uses the file's own directory
// to construct an absolute path to the project root.
// This avoids issues with DOCUMENT_ROOT varying between environments (e.g., Apache vs. CLI).
$env = [];
// __DIR__ is the directory of the current file (backend/src).
// We go up two levels to get to the project root.
$dotenvPath = dirname(__DIR__, 2) . '/.env';

if (file_exists($dotenvPath)) {
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
} else {
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