<?php
require_once 'config.php';

if (!function_exists('log_message')) {
    function log_message($message) {
        $timestamp = date("Y-m-d H:i:s");
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $log_entry = "[$timestamp] " . $message . "\n";
        file_put_contents(__DIR__ . '/debug.log', $log_entry, FILE_APPEND);
    }
}

function get_db() {
    static $db = null;
    if ($db === null) {
        log_message("DB: Attempting to connect...");
        $db = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if ($db->connect_errno) {
            log_message("DB: Connection Failed! Error: " . $db->connect_error);
            return null;
        }
        log_message("DB: Connection Successful.");
        $db->set_charset('utf8mb4');
    }
    return $db;
}
?>
