<?php
/**
 * 文件名: db.php
 * 路径: backend/core/db.php
 */
function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        // --- 核心逻辑：加载 .env ---
        // 1. 定义 .env 文件在服务器上的物理路径
        //    __DIR__ 是 .../public_html/core
        //    所以 .env 在上两级目录
        $dotenvPath = __DIR__ . '/../../.env';
        
        // 2. 解析 .env 文件
        $config = [];
        if (is_readable($dotenvPath)) {
            $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $config[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
                }
            }
        } else {
             // 如果 .env 找不到，就停止
             $error_msg = "DB config error: .env file not found at " . $dotenvPath;
             error_log($error_msg);
             if (php_sapi_name() !== 'cli') {
                 http_response_code(500);
                 exit(json_encode(['message' => 'Server configuration error.']));
             }
             die($error_msg . "\n");
        }

        // 3. 使用解析出的配置进行连接
        $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        try {
            $conn = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
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
?>