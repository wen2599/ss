<?php
function get_db_connection() {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $name = getenv('DB_NAME');
    
    $mysqli = new mysqli($host, $user, $pass, $name);
    
    if ($mysqli->connect_error) {
        // 在生产环境中，不应直接暴露错误信息
        error_log("Database connection failed: " . $mysqli->connect_error);
        die("Could not connect to database.");
    }
    
    $mysqli->set_charset("utf8mb4");
    return $mysqli;
}