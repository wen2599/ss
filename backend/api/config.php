<?php
// backend/api/config.php

/**
 * Application Configuration
 *
 * IMPORTANT: EDIT THIS FILE WITH YOUR CREDENTIALS
 * This file contains the configuration settings for the application.
 * Unlike the previous .env system, you must now hardcode your credentials here.
 */

// --- Database Configuration ---
// Replace the placeholder values with your actual database credentials.
define('DB_HOST', 'your_database_host');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// --- Telegram Bot Configuration ---
// Replace the placeholder values with your actual Telegram bot credentials.
define('TELEGRAM_BOT_TOKEN', 'your_telegram_bot_token');
define('TELEGRAM_CHANNEL_ID', 'your_telegram_channel_id');
define('TELEGRAM_SUPER_ADMIN_ID', 'your_telegram_super_admin_id');

// Note: The closing PHP tag is intentionally omitted from this file.
// This is a best practice in PHP for files that contain only PHP code.
// It prevents accidental whitespace from being sent to the browser, which
// can cause "headers already sent" errors.
