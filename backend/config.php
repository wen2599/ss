<?php
// backend/config.php

// --- Security ---
// The secret for communicating with the Cloudflare Worker.
define('WORKER_SECRET', getenv('WORKER_SECRET') ?: '816429fb-1649-4e48-9288-7629893311a6');

// --- Database Credentials ---
// These are loaded from the .env file via bootstrap.php.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');
define('DB_NAME', getenv('DB_NAME') ?: 'your_db_name');

// --- Telegram Bot ---
// Credentials for the administration bot.
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_ADMIN_ID', getenv('TELEGRAM_ADMIN_ID'));

// --- File Uploads ---
// The directory where email files and attachments will be stored.
define('UPLOADS_DIR', __DIR__ . '/uploads');
?>