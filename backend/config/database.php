<?php
// 加载 .env (如果尚未加载)
if (empty(getenv('DB_HOST'))) {
    // 简单的 .env 加载逻辑
    function loadEnv($path) {
        if (!file_exists($path)) {
            throw new Exception('.env file not found');
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    loadEnv(__DIR__ . '/../.env');
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