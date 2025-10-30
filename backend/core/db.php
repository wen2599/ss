<?php
// 文件名: db.php
// 路径: core/db.php

function get_db_connection() {
    static $conn = null;

    if ($conn === null) {
        // __DIR__ 是 .../core/ , 所以 config.php 在上一级 (项目根目录)
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
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');

            // 在 Web 请求中返回 JSON 错误
            if (php_sapi_name() !== 'cli') {
                echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: Database connection failed.']);
            } else {
                // 在命令行中直接输出错误
                echo "DB Connection Error: " . $e->getMessage() . "\n";
            }
            exit;
        }
    }

    return $conn;
}