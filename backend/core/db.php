<?php
// 文件名: db.php
// 路径: core/db.php
function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        require_once __DIR__ . '/../config.php'; 
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            if (php_sapi_name() !== 'cli') {
                http_response_code(500);
                exit(json_encode(['message' => 'Database connection failed.']));
            }
            die("DB Connection Error: " . $e->getMessage() . "\n");
        }
    }
    return $conn;
}