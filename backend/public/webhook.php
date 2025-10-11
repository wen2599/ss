<?php

// --- Force Error Reporting and Debug Logging ---

// Ensure all errors are displayed and logged.
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error_log.log');
error_reporting(E_ALL);

// A simple, self-contained logging function for this wrapper.
function wrapper_log($message) {
    $log_file = dirname(__DIR__) . '/error_log.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = is_string($message) ? $message : print_r($message, true);
    file_put_contents($log_file, "[$timestamp] [WRAPPER] " . $log_message . "\n", FILE_APPEND);
}

wrapper_log("Webhook wrapper started.");

try {
    // Attempt to include the main webhook handler.
    // If this file has a fatal parse error, the catch block will not be reached,
    // but the error should be logged to the file specified in 'error_log'.
    require_once __DIR__ . '/../src/api/telegramWebhook.php';
    wrapper_log("Successfully included telegramWebhook.php.");
} catch (Throwable $t) {
    // Catch any throwable error (includes Errors and Exceptions)
    wrapper_log("Caught a throwable: " . $t->getMessage());
    http_response_code(500);
}

wrapper_log("Webhook wrapper finished.");