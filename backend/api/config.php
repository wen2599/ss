<?php
// backend/api/config.php

/**
 * Application Configuration
 */

// --- Database Configuration ---
// Defines the connection details for the MySQL database.
define('DB_HOST', 'your_database_host');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// --- Telegram Bot Configuration (Optional) ---
// These are not used in the current application logic but are kept for potential future use.
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN');
define('TELEGRAM_CHANNEL_ID', 'YOUR_CHANNEL_ID');

// --- Game Logic Configuration ---
// Payout multiplier for winning bets.
define('PAYOUT_MULTIPLIER', 40);

?>
