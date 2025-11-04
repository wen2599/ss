<?php
// setup.php - 数据库表初始化脚本

// 加载 .env 配置
$env = parse_ini_file('.env');
if ($env === false) {
    die("Error: Cannot read .env file.\n");
}

$db_host = $env['DB_HOST'];
$db_user = $env['DB_USER'];
$db_pass = $env['DB_PASS'];
$db_name = $env['DB_NAME'];

// 创建数据库连接
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 检查连接
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

// SQL to create table
$sql = "
CREATE TABLE IF NOT EXISTS lottery_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'lottery_numbers' created successfully or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();