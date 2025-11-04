<?php
// db.php - 数据库连接模块

function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        $env = parse_ini_file(__DIR__ . '/.env');
        if ($env === false) {
            error_log("Failed to parse .env file");
            return null;
        }

        $conn = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);

        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return null;
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}