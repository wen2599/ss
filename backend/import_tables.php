<?php
// 在运行此脚本前，请确保已在 backend/.env 文件中配置好数据库信息
require_once __DIR__ . '/config/load_env.php';
require_once __DIR__ . '/config/database.php';

// 获取数据库连接
$mysqli = get_db_connection();

if ($mysqli->connect_error) {
    die("数据库连接失败: " . $mysqli->connect_error . "\n");
}

echo "数据库连接成功。\n";

// -- 创建 users 表 --
$sql_users = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($mysqli->query($sql_users) === TRUE) {
    echo "数据表 'users' 创建成功或已存在。\n";
} else {
    echo "创建数据表 'users' 失败: " . $mysqli->error . "\n";
}

// -- 创建 lottery_numbers 表 --
$sql_lottery = "
CREATE TABLE IF NOT EXISTS `lottery_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `number` VARCHAR(255) NOT NULL,
  `source_channel_id` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($mysqli->query($sql_lottery) === TRUE) {
    echo "数据表 'lottery_numbers' 创建成功或已存在。\n";
} else {
    echo "创建数据表 'lottery_numbers' 失败: " . $mysqli->error . "\n";
}

$mysqli->close();
echo "脚本执行完毕。\n";