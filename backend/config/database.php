<?php
// backend/config/database.php

require_once __DIR__ . '/secrets.php';

function get_db_connection() {
    $servername = get_env('DB_HOST');
    $username = get_env('DB_USER');
    $password = get_env('DB_PASS');
    $dbname = get_env('DB_NAME');

    if (!$servername || !$username || !$password || !$dbname) {
        throw new Exception("Database configuration is incomplete.");
    }

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    return $conn;
}
?>
