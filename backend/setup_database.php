<?php
// setup_database.php

// --- 初始化 ---
// 该脚本应放在 'backend' 目录中, 然后通过命令行执行: php setup_database.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入应用配置文件
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/helpers.php';

echo "--> 正在尝试连接数据库...\n";

// --- 数据库连接 ---
$conn = get_db_connection();
if (!$conn) {
    echo "!!! 错误: 数据库连接失败! 请仔细检查 .env 文件中的 DB_HOST, DB_USER, DB_PASS, DB_NAME 是否正确。\n";
    exit(1); // 退出脚本
}
echo "--> ✅ 成功: 数据库已连接。\n\n";

// --- 定义需要创建的数据表 ---
$tables_sql = [
    'lottery_numbers' => "
        CREATE TABLE `lottery_numbers` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `issue` VARCHAR(255) NOT NULL UNIQUE,
          `numbers` JSON NOT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'users' => "
        CREATE TABLE `users` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `username` VARCHAR(255) NOT NULL UNIQUE,
          `password` VARCHAR(255) NOT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'allowed_emails' => "
        CREATE TABLE `allowed_emails` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `email` VARCHAR(255) NOT NULL UNIQUE,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
];

// --- 循环执行创建表的操作 ---
foreach ($tables_sql as $table_name => $sql_command) {
    echo "--> 正在检查数据表: `{$table_name}`...\n";

    // 检查表是否已存在
    $result = $conn->query("SHOW TABLES LIKE '{$table_name}'");
    if ($result && $result->num_rows > 0) {
        echo "--> 提示: 数据表 `{$table_name}` 已经存在, 无需创建。\n\n";
        continue;
    }

    // 如果不存在, 则执行创建
    echo "--> 正在创建数据表: `{$table_name}`...\n";
    if ($conn->query($sql_command)) {
        echo "--> ✅ 成功: 数据表 `{$table_name}` 已成功创建。\n\n";
    } else {
        echo "!!! 错误: 创建数据表 `{$table_name}` 失败! 错误原因: " . $conn->error . "\n\n";
    }
}

// --- 清理 ---
$conn->close();
echo "--> 所有检查已完成。\n";

?>
