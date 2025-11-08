<?php
// File: backend/db_operations.php
if (defined('DB_OPERATIONS_LOADED')) return;

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), $options);
    }
    return $pdo;
}
define('DB_OPERATIONS_LOADED', true);