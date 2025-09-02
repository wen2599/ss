<?php
require_once 'config.php';

function get_db() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        if ($db->connect_errno) {
            die('DB Error: ' . $db->connect_error);
        }
        $db->set_charset('utf8mb4');
    }
    return $db;
}
?>
