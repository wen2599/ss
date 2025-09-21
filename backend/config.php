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

// 2. Database Connection Settings
// Replace the following placeholders with your actual database credentials.
$db_host = 'localhost';     // Database host (e.g., '127.0.0.1' or 'localhost')
$db_name = 'your_database_name'; // The name of your database
$db_user = 'your_username';       // Your database username
$db_pass = 'your_password';       // Your database password

/**
 * You can establish a database connection using these variables.
 * Example using PDO:
 *
 * try {
 *     $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
 *     // Set the PDO error mode to exception
 *     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
 * } catch(PDOException $e) {
 *     // Log the error or handle it as needed
 *     // For security, do not display detailed errors in a production environment
 *     error_log("Database connection failed: " . $e->getMessage());
 *     die("Could not connect to the database. Please check the configuration.");
 * }
 */

?>
