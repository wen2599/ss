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
$admin_id = 'YOUR_ADMIN_USER_ID'; 

// 3. Cloudflare Worker Secret
// This secret must exactly match the WORKER_SECRET in your Cloudflare Worker script.
$worker_secret = 'A_VERY_SECRET_KEY';

// 4. Upload Directory
// Defines the path where files uploaded from the worker will be stored.
// Ensure this directory exists and is writable by the web server.
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// 5. Database Connection Settings
// Replace the following placeholders with your actual database credentials.
$db_host = 'localhost';     // Database host (e.g., '127.0.0.1' or 'localhost')
$db_name = 'your_database_name'; // The name of your database
$db_user = 'your_username';       // Your database username
$db_pass = 'your_password';       // Your database password

// 6. Database Schema
//
// CREATE TABLE `users` (
//   `id` INT AUTO_INCREMENT PRIMARY KEY,
//   `username` VARCHAR(255) NULL UNIQUE,
//   `email` VARCHAR(255) NOT NULL UNIQUE,
//   `password` VARCHAR(255) NOT NULL,
//   `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// );

?>
