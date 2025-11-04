<?php
// setup.php - 数据库表初始化脚本

// 警告：运行此脚本将删除现有的 lottery_results 表！
// 在生产环境中请谨慎操作。

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
$conn->set_charset("utf8mb4");

// 新的表名和结构
$tableName = "lottery_results";

// SQL to drop old table if it exists
$dropSql = "DROP TABLE IF EXISTS $tableName;";
if ($conn->query($dropSql) === TRUE) {
    echo "Table '$tableName' dropped successfully (if it existed).\n";
} else {
    echo "Error dropping table: " . $conn->error . "\n";
}


// SQL to create new table
$createSql = "
CREATE TABLE IF NOT EXISTS lottery_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lottery_type VARCHAR(100) NOT NULL,
    issue_number VARCHAR(50) NOT NULL,
    winning_numbers VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($createSql) === TRUE) {
    echo "Table '$tableName' created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
