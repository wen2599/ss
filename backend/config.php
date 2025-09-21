<?php

/**
 * Configuration File
 *
 * This file contains the essential settings for the application,
 * including the Telegram bot token and database credentials.
 */

// 1. Telegram Bot Token
// Replace 'YOUR_TELEGRAM_BOT_TOKEN' with the token you get from BotFather on Telegram.
$bot_token = 'YOUR_TELEGRAM_BOT_TOKEN';

// 2. Telegram Admin User ID
// Replace 'YOUR_ADMIN_USER_ID' with your own numeric Telegram User ID.
// This is used to restrict access to sensitive commands.
// You can get your ID by messaging @userinfobot on Telegram.
$admin_id = 'YOUR_ADMIN_USER_ID'; 

// 3. Database Connection Settings
// Replace the following placeholders with your actual database credentials.
$db_host = 'localhost';     // Database host (e.g., '127.0.0.1' or 'localhost')
$db_name = 'your_database_name'; // The name of your database
$db_user = 'your_username';       // Your database username
$db_pass = 'your_password';       // Your database password

?>
