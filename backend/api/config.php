<?php
// backend/api/config.php

/**
 * Application Configuration
 */

// --- Database Configuration ---
// Defines the connection details for the MySQL database.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'lottery_db');
define('DB_USER', 'lottery_user');
define('DB_PASS', 'password');

// --- Telegram Bot Configuration (Optional) ---
// These are not used in the current application logic but are kept for potential future use.
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN');
define('TELEGRAM_CHANNEL_ID', 'YOUR_CHANNEL_ID');
define('TELEGRAM_SUPER_ADMIN_ID', 1878794912); // Replace with your own Telegram User ID

// --- Game Logic Configuration ---
// Payout multiplier for winning bets.
define('PAYOUT_MULTIPLIER', 40);

?>
