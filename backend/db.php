<?php
// db.php - Final Robust Version

function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        $env_path = __DIR__ . '/.env';
        $env = parse_ini_file($env_path);

        if ($env === false) {
            error_log("FATAL in db.php: Could not read or parse .env file.");
            return null;
        }

        $db_host = $env['DB_HOST'] ?? null;
        $db_user = $env['DB_USER'] ?? null;
        $db_pass = $env['DB_PASS'] ?? null;
        $db_name = $env['DB_NAME'] ?? null;

        if (!$db_host || !$db_user || !$db_pass || !$db_name) {
            error_log("FATAL in db.php: DB credentials missing in .env.");
            return null;
        }

        // 建立 mysqli 连接
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

        if ($conn->connect_error) {
            error_log("DATABASE CONNECTION FAILED in db.php: " . $conn->connect_error);
            $conn = null; // 确保下次调用会重试
            return null;
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}