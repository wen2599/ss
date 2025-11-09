<?php
// File: backend/config.php
if (defined('CONFIG_LOADED')) return;

// --- .env Loader ---
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), ';') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $value = trim($value, "\"");
            putenv(trim($name) . "=$value");
        }
    }
}

// --- Error Handling ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);

define('CONFIG_LOADED', true);