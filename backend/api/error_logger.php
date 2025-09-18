<?php
// backend/api/error_logger.php

/**
 * A simple error logging function that writes to a local file.
 * This provides a self-contained way to view errors without needing server-level access.
 *
 * @param string $message The error message to log.
 */
function log_error(string $message) {
    // Define the path to the log file, relative to this script.
    // Ensure the logs directory exists.
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/error.log';

    // Format the log entry with a timestamp.
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] " . $message . "\n";

    // Append the entry to the log file.
    // The FILE_APPEND flag is crucial to avoid overwriting the log on each call.
    // The LOCK_EX flag prevents other processes from writing to the file at the same time.
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Set global error and exception handlers to use our custom logger.
 * This should be called once at the start of a script.
 */
function register_error_handlers() {
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) { return; }
        log_error("Error: [$severity] $message in $file on line $line");
        return true; // Don't execute PHP internal error handler
    });

    set_exception_handler(function($exception) {
        log_error("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
        // Depending on the context, you might want to show a generic error page.
        // For an API, you might want to send a JSON response.
        if (php_sapi_name() !== 'cli') {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred.']);
        }
    });
}
