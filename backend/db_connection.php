<?php
require_once __DIR__ . '/env_loader.php';

function getDbConnection() {
    $host = getenv('DB_HOST');
    $db = getenv('DB_DATABASE');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        // In a real application, you'd log this error instead of echoing it.
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}
