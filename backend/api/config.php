<?php
// backend/api/config.php

/**
 * Application Configuration
 */

// --- Database Configuration ---
// Defines the connection details for the MySQL database.
define('DB_HOST', 'mysql12.serv00.com');
define('DB_NAME', 'm1030');
define('DB_USER', 'm1030');
define('DB_PASS', 'Wenx*');

// --- Telegram Bot Configuration (Optional) ---
// These are not used in the current application logic but are kept for potential future use.
define('TELEGRAM_BOT_TOKEN', '7279950407:AAGo');
define('TELEGRAM_CHANNEL_ID', '-1002652392716');
define('TELEGRAM_SUPER_ADMIN_ID', 1878794912); // Replace with your own Telegram User ID

// --- Game Logic Configuration ---
// Payout multiplier for winning bets.
define('PAYOUT_MULTIPLIER', 40);

?>
