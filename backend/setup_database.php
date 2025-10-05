
<?php
// setup_database.php

// --- 初始化 ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config.php';

echo "--> 正在尝试连接数据库...\n";

// --- 数据库连接 ---
$conn = get_db_connection();
if (!$conn) {
    echo "!!! 错误: 数据库连接失败! 请仔细检查 .env 文件中的配置。\n";
    exit(1);
}
echo "--> ✅ 成功: 数据库已连接。\n\n";

// --- 升级步骤: 删除旧的 lottery_numbers 表 ---
echo "--> 正在检查过时的 `lottery_numbers` 表...\n";
if ($conn->query("SHOW TABLES LIKE 'lottery_numbers'")->num_rows > 0) {
    echo "--> 发现过时的 `lottery_numbers` 表，正在删除...\n";
    if ($conn->query("DROP TABLE `lottery_numbers`")) {
        echo "--> ✅ 成功: 过时的 `lottery_numbers` 表已删除。\n\n";
    } else {
        echo "!!! 错误: 删除过时的 `lottery_numbers` 表失败。错误: " . $conn->error . "\n\n";
    }
} else {
    echo "--> 正常: 未发现过时的 `lottery_numbers` 表。\n\n";
}

// --- 数据表定义 ---
$tables_sql = [
    'users' => "
        CREATE TABLE IF NOT EXISTS `users` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `email` VARCHAR(255) NOT NULL UNIQUE,
          `password` VARCHAR(255) NOT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `last_login_time` TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'allowed_emails' => "
        CREATE TABLE IF NOT EXISTS `allowed_emails` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `email` VARCHAR(255) NOT NULL UNIQUE,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'lottery_results' => "
        CREATE TABLE IF NOT EXISTS `lottery_results` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `lottery_type` VARCHAR(100) NOT NULL,
            `issue` VARCHAR(100) NOT NULL,
            `numbers` JSON NOT NULL,
            `zodiacs` JSON NOT NULL,
            `colors` JSON NOT NULL,
            `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `type_issue` (`lottery_type`, `issue`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'emails' => "
        CREATE TABLE IF NOT EXISTS `emails` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `message_id` VARCHAR(255) UNIQUE,
            `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `from_address` VARCHAR(255),
            `subject` VARCHAR(255),
            `body_html` TEXT,
            `body_text` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'betting_slips' => "
        CREATE TABLE IF NOT EXISTS `betting_slips` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email_id` INT NOT NULL,
            `raw_text` VARCHAR(1000) NOT NULL,
            `parsed_data` JSON,
            `is_valid` BOOLEAN NOT NULL DEFAULT FALSE,
            FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    "
];

// --- 循环创建或验证数据表 ---
foreach ($tables_sql as $table_name => $sql_command) {
    echo "--> 正在检查/创建数据表: `{$table_name}`...\n";
    if ($conn->query($sql_command)) {
        echo "--> ✅ 成功: 数据表 `{$table_name}` 已存在或已成功创建。\n\n";
    } else {
        echo "!!! 错误: 处理数据表 `{$table_name}` 时出错! 原因: " . $conn->error . "\n\n";
    }
}

// --- 升级步骤: 为 users 表添加 last_login_time ---
echo "--> 正在检查 `users` 表是否需要 `last_login_time` 字段...\n";
$result = $conn->query("SHOW COLUMNS FROM `users` LIKE 'last_login_time'");
if ($result->num_rows == 0) {
    echo "--> 正在为 `users` 表添加 `last_login_time` 字段...\n";
    if ($conn->query("ALTER TABLE `users` ADD COLUMN `last_login_time` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`")) {
        echo "--> ✅ 成功: `last_login_time` 字段已添加。\n\n";
    } else {
        echo "!!! 错误: 添加 `last_login_time` 字段失败。错误: " . $conn->error . "\n\n";
    }
} else {
    echo "--> 正常: `users` 表已有 `last_login_time` 字段。\n\n";
}


$conn->close();
echo "--> 所有数据库操作已完成。\n";

?>
