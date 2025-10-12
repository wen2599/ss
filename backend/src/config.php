<?php

// --- UNIFIED BOOTSTRAP ---
// This file is now the single source of truth for application initialization.

// --- Session Start ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Global Error & Exception Handling ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    error_log("Unhandled Exception: " . $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'An internal server error occurred.']);
    }
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Core File Includes ---
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Telegram.php';

// --- Global Constants Definition ---
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_DATABASE', 'my_database');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('TELEGRAM_BOT_TOKEN', null); // Replace with your bot token if needed
define('TELEGRAM_WEBHOOK_SECRET', null);
define('TELEGRAM_CHANNEL_ID', null);
define('TELEGRAM_ADMIN_ID', null);

// --- Global Request Body ---
if (php_sapi_name() !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
    }
}
