<?php
// config.php

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'lottery');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Telegram configuration
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '');
define('TELEGRAM_ADMIN_ID', getenv('TELEGRAM_ADMIN_ID') ?: '');

// Function to send a JSON response
function send_json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}