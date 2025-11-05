<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

try {
    $pdo = get_db_connection();
    echo "数据库连接成功...\n";

    $sql = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `winning_numbers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `issue_number` VARCHAR(50) NOT NULL UNIQUE,
        `numbers` VARCHAR(255) NOT NULL,
        `draw_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `bets_raw_emails` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `email_content` TEXT NOT NULL,
        `status` ENUM('pending', 'processing', 'processed', 'error') DEFAULT 'pending',
        `ai_result` JSON,
        `error_message` TEXT,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `settlements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `issue_number` VARCHAR(50) NOT NULL,
        `bet_email_id` INT NOT NULL,
        `winning_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        `details` JSON,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
        FOREIGN KEY (`bet_email_id`) REFERENCES `bets_raw_emails`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- AI模板表，高级功能，可后续实现
    CREATE TABLE IF NOT EXISTS `ai_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_hash` VARCHAR(64) NOT NULL UNIQUE,
        `parsing_logic` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "所有数据表创建或检查成功！\n";

} catch (PDOException $e) {
    die("数据库错误: " . $e->getMessage() . "\n");
}
