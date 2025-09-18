<?php
// backend/api/config.php

/**
 * Application Configuration
 *
 * This file loads configuration from a .env file located in the `backend` directory.
 * Do not hardcode credentials here. Create a .env file based on .env.example.
 */

// Load the environment variables
require_once __DIR__ . '/env_loader.php';

// --- Database Configuration ---
// Values are loaded from the .env file.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_DATABASE') ?: null);
define('DB_USER', getenv('DB_USERNAME') ?: null);
define('DB_PASS', getenv('DB_PASSWORD') ?: null);

// --- Telegram Bot Configuration ---
// Values are loaded from the .env file.
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: null);
define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: null);

// --- CORS Configuration ---
// The frontend URL is needed for setting the Access-Control-Allow-Origin header.
define('FRONTEND_URL', getenv('FRONTEND_URL') ?: null);

// --- Super Admin ---
// This can be left hardcoded if it's considered a system-level constant and not a secret
define('TELEGRAM_SUPER_ADMIN_ID', getenv('TELEGRAM_SUPER_ADMIN_ID') ?: 1878794912);

// Note: The closing PHP tag is intentionally omitted from this file.
?>
