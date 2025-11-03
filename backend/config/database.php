<?php
// backend/config/database.php

// 确保配置已加载
if (file_exists(__DIR__ . '/../utils/config_loader.php')) {
    require_once __DIR__ . '/../utils/config_loader.php';
}

function getDbConnection() {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $db = getenv('DB_NAME');
    
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        error_log("DB Connection Error: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
        exit();
    }
    
    return $conn;
}
?>