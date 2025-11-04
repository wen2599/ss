<?php
// setup_db.php

// 加载 .env 配置
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die(".env file not found. Please create one.\n");
}
$env = parse_ini_file($envFile);

// 数据库连接信息
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

echo "Database connected successfully.\n";

// --- 创建 users 表 ---
$sql_users = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql_users) === TRUE) {
    echo "Table 'users' created successfully or already exists.\n";
} else {
    die("Error creating table 'users': " . $conn->error . "\n");
}

// --- 创建 lottery_numbers 表 ---
$sql_lottery = "
CREATE TABLE IF NOT EXISTS `lottery_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `issue_number` VARCHAR(50) NOT NULL UNIQUE COMMENT '期号',
  `winning_numbers` VARCHAR(255) NOT NULL COMMENT '开奖号码,逗号分隔',
  `draw_date` DATE NOT NULL COMMENT '开奖日期',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql_lottery) === TRUE) {
    echo "Table 'lottery_numbers' created successfully or already exists.\n";
} else {
    die("Error creating table 'lottery_numbers': " . $conn->error . "\n");
}

echo "Database setup complete.\n";

$conn->close();
?>