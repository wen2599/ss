<?php
require_once 'config.php';

function get_db() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        if ($db->connect_errno) {
            // Since this function can be called from index.php where send_json_error is defined,
            // we can use it for a graceful JSON error response.
            // Note: This creates a dependency on the calling script's context.
            if (function_exists('send_json_error')) {
                send_json_error(500, 'Database connection failed', $db->connect_error);
            } else {
                die('DB Error: ' . $db->connect_error);
            }
        }
        $db->set_charset('utf8mb4');
    }
    return $db;
}
?>
