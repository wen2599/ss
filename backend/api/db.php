<?php
require_once 'config.php';

// It's not ideal to redefine this, but for simple debugging it's ok.
if (!function_exists('log_message')) {
    function log_message($message) {
        $timestamp = date("Y-m-d H:i:s");
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $log_entry = "[$timestamp] " . $message . "\n";
        file_put_contents('debug.log', $log_entry, FILE_APPEND);
    }
}


function get_db() {
    static $db = null;
    if ($db === null) {
        log_message("Attempting to connect to database with user: " . ($DB_USER ?? 'N/A') . " and db: " . ($DB_NAME ?? 'N/A'));

        // Use @ to suppress the default warning on failure, as we are handling it.
        $db = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($db->connect_errno) {
            log_message("Database connection failed! Error: " . $db->connect_error);
            // We should not die() here as it gives a blank 500 page.
            // Return null and let the caller handle it.
            return null;
        }

        log_message("Database connection successful.");
        $db->set_charset('utf8mb4');
    }
    return $db;
}
?>
