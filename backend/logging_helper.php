<?php
// backend/logging_helper.php

/**
 * Custom logging function to handle potential file permission errors.
 *
 * @param string $message The message to log.
 * @param string $level The log level (e.g., 'INFO', 'WARNING', 'ERROR').
 */
function custom_log(string $message, string $level = 'INFO')
{
    $log_file = __DIR__ . '/../app_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] - $message" . PHP_EOL;

    // Check if the directory is writable, otherwise log to the default error log
    if (!is_writable(dirname($log_file))) {
        error_log("Log directory is not writable. Falling back to default log. Message: $message", 0);
        return; // Exit the function to avoid trying to write to an unwritable file
    }

    // Check if the file itself is writable
    if (file_exists($log_file) && !is_writable($log_file)) {
        error_log("Log file '$log_file' is not writable. Message: $message", 0);
        return; // Exit the function
    }

    // Attempt to write to the custom log file
    // Use error suppression (@) because we have already checked for writability,
    // but there could be a race condition. The fallback is the system logger.
    if (@file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
        // If writing fails for any reason, fall back to the default PHP error log
        error_log("Failed to write to custom log file. Message: $message", 0);
    }
}
