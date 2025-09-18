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
    $log_file = __DIR__ . '/../logs/error.log';

    // Format the log entry with a timestamp.
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] " . $message . "\n";

    // Append the entry to the log file.
    // The FILE_APPEND flag is crucial to avoid overwriting the log on each call.
    // The LOCK_EX flag prevents other processes from writing to the file at the same time.
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
