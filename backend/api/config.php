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
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// --- Telegram Bot Configuration ---
// Replace with your actual Telegram bot information.
define('TELEGRAM_BOT_TOKEN', '123456789:ABCdefGHIjklMNOpqrSTUvwxYZ');
define('TELEGRAM_CHANNEL_ID', '-1001234567890'); // 频道或群组的chat_id（一般为负数）
define('TELEGRAM_SUPER_ADMIN_ID', 1878794912);   // 你的Telegram数字ID

// Note: The closing PHP tag is intentionally omitted from this file.
