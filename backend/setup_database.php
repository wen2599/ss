<?php
// 此脚本应通过 SSH 命令行执行: php /path/to/backend/setup_database.php

// 严格错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 加载 .env 文件的函数
function load_env() {
    $env_path = __DIR__ . '/../../.env'; // 指向服务器根目录的 .env
    if (!file_exists($env_path)) {
        die(".env file not found at {$env_path}. Please create it.\n");
    }
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

load_env();

// 数据库连接信息
$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASS'];
$db_name = $_ENV['DB_NAME'];

// 创建连接
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Database connected successfully.\n";

// --- 创建 lottery_results 表 ---
$sql_lottery = "
CREATE TABLE IF NOT EXISTS lottery_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    issue_number VARCHAR(50) UNIQUE NOT NULL,
    draw_date DATE,
    numbers VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

if ($conn->query($sql_lottery) === TRUE) {
    echo "Table 'lottery_results' created successfully or already exists.\n";
} else {
    echo "Error creating table 'lottery_results': " . $conn->error . "\n";
}

// --- 创建 emails 表 ---
$sql_emails = "
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE,
    from_address VARCHAR(255) NOT NULL,
    subject TEXT,
    body_text LONGTEXT,
    body_html LONGTEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

if ($conn->query($sql_emails) === TRUE) {
    echo "Table 'emails' created successfully or already exists.\n";
} else {
    echo "Error creating table 'emails': " . $conn->error . "\n";
}

$conn->close();
echo "Setup script finished.\n"
?>