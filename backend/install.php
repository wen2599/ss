<?php
// 文件名: install.php
// 路径: backend/install.php
// 更新: 修复了最后一条 INSERT ... ON DUPLICATE KEY UPDATE 语句的语法问题

// 仅限命令行运行
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once 'config.php';
require_once 'core/db.php';

$db = get_db_connection();

echo "Starting database installation...\n";

$sql_statements = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `is_authorized` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `lottery_results` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `issue_number` VARCHAR(50) NOT NULL UNIQUE,
        `winning_numbers` VARCHAR(255) NOT NULL COMMENT '逗号分隔的6个平码',
        `special_number` VARCHAR(10) NOT NULL COMMENT '特码',
        `draw_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `received_emails` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `issue_number` VARCHAR(50) NULL,
        `from_address` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255),
        `raw_content` TEXT,
        `structured_data` JSON,
        `settlement_result` JSON,
        `total_bet_amount` DECIMAL(10, 2) DEFAULT 0.00,
        `total_win_amount` DECIMAL(10, 2) DEFAULT 0.00,
        `status` ENUM('new', 'structured', 'settled', 'error') NOT NULL DEFAULT 'new',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `key_name` VARCHAR(100) NOT NULL UNIQUE,
        `key_value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // --- 关键修改在这里 ---
    // 旧语法: ON DUPLICATE KEY UPDATE key_name = key_name; (有歧义)
    // 新语法: 使用 VALUES() 函数，这是处理重复键更新的标准且无歧义的方法。
    // 它的意思是：如果 key_name 已经存在，就用本次 INSERT 尝试插入的值 (VALUES(key_value)) 来更新 key_value 字段。
    // 我们这里让它用自己的值更新自己，实际上就是“什么都不做”，安全地跳过重复插入。
    "INSERT INTO `settings` (`key_name`, `key_value`) VALUES ('gemini_api_key', '') 
     ON DUPLICATE KEY UPDATE `key_value` = VALUES(`key_value`);"
];

foreach ($sql_statements as $sql) {
    try {
        $db->exec($sql);
        echo "Successfully executed query.\n";
    } catch (PDOException $e) {
        die("Error executing query: " . $e->getMessage() . "\nSQL: " . $sql . "\n");
    }
}

echo "Database installation completed successfully!\n";