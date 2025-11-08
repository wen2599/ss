<?php
// File: backend/db_operations.php (Final Standardized Version)

if (defined('DB_OPERATIONS_LOADED')) return;
define('DB_OPERATIONS_LOADED', true);

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        // 【关键修改】确保我们读取的是标准化的变量名
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            config('DB_HOST'),
            config('DB_PORT'),
            config('DB_DATABASE') // 读取 DB_DATABASE
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        
        // 读取 DB_USERNAME 和 DB_PASSWORD
        $pdo = new PDO($dsn, config('DB_USERNAME'), config('DB_PASSWORD'), $options);
    }
    return $pdo;
}