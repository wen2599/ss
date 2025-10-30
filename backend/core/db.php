<?php
/**
 * 文件名: db.php
 * 路径: backend/core/db.php
 * 版本: Final
 */
function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        // 使用硬编码的配置，彻底排除 .env 读取问题，确保最高稳定性
        $db_host = 'mysql12.serv00.com';
        $db_user = 'm10300_yh';
        $db_pass = 'Wenxiu1234*';
        $db_name = 'm10300_newdb';
        $db_port = 3306;
        
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        try {
            $conn = new PDO($dsn, $db_user, $db_pass, $options);
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