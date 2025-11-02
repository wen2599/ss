<?php
// backend/setup_database.php

// --- 安全检查：确保此脚本只能从命令行运行 ---
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/config.php';

echo "Starting database setup...\n";

// --- SQL语句数组 ---
// 将每个表的创建语句分开，便于调试和执行
$sql_statements = [
    // 1. 用户表 (users)
    // 移除了全局赔率，增加了用户专属的 odds_settings (JSON格式)
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `odds_settings` JSON NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 2. 用户原始邮件表 (user_emails)
    "CREATE TABLE IF NOT EXISTS `user_emails` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
    from_sender VARCHAR(255),
    subject VARCHAR(255),
        `raw_email` LONGTEXT NOT NULL,
        `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `status` VARCHAR(50) NOT NULL DEFAULT 'new',
        `error_message` TEXT NULL,
        INDEX `idx_user_id` (`user_id`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 3. 邮件解析批次表 (email_batches)
    // 增加了 user_id, 这样我们就知道这笔投注属于哪个用户，以便使用他的赔率
    "CREATE TABLE IF NOT EXISTS `email_batches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `user_email_id` INT NOT NULL,
        `issue_number` VARCHAR(50) NULL,
        `parsed_data` JSON NULL,
        `settlement_result` JSON NULL,
        `status` VARCHAR(50) NOT NULL DEFAULT 'new',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `processed_at` TIMESTAMP NULL,
        `settled_at` TIMESTAMP NULL,
        INDEX `idx_user_email_id` (`user_email_id`),
        INDEX `idx_issue_number` (`issue_number`),
        INDEX `idx_status` (`status`),
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // 4. 开奖结果表 (lottery_results)
    "CREATE TABLE IF NOT EXISTS `lottery_results` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `issue_number` VARCHAR(50) NOT NULL UNIQUE,
        `numbers` VARCHAR(255) NOT NULL,
        `draw_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 5. (可选) 删除旧的全局赔率表
    "DROP TABLE IF EXISTS `odds`;"
];

// --- 执行SQL ---
try {
    foreach ($sql_statements as $index => $sql) {
        $pdo->exec($sql);
        echo "Successfully executed statement " . ($index + 1) . ".\n";
    }
    echo "✅ Database setup completed successfully!\n";
} catch (PDOException $e) {
    die("❌ Database error: " . $e->getMessage() . "\n");
}
