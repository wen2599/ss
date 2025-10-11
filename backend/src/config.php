<?php

// Global Configuration File

require_once __DIR__ . '/core/DotEnv.php';

// --- Load Environment Variables ---
// This is the definitive, robust path fix. It starts from this file's directory
// and walks up the directory tree to find the project root where .env is located.
$env = [];
$currentDir = __DIR__; // Start in the current directory (/src)
$projectRoot = null;

// Traverse up from /src to find the project root (the one containing .env)
while (true) {
    if (file_exists($currentDir . '/.env')) {
        $projectRoot = $currentDir;
        break;
    }
    // Go one level up
    $parentDir = dirname($currentDir);
    // If we've reached the top of the filesystem and haven't found it, stop.
    if ($parentDir === $currentDir) { 
        break;
    }
    $currentDir = $parentDir;
}

if ($projectRoot) {
    $dotenvPath = $projectRoot . '/.env';
    error_log("Config: Found .env file at: " . $dotenvPath);
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
} else {
    $errorMessage = "CRITICAL: .env file could not be found in any parent directory.";
    error_log($errorMessage);
    throw new \RuntimeException($errorMessage);
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
