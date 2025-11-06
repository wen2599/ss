<?php
// A simple script to create necessary database tables.
// Run this once via SSH: php setup/import_tables.php

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/db.php';

try {
    $pdo = get_db_connection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `emails` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `raw_content` TEXT NOT NULL,
        `status` ENUM('pending', 'processed', 'failed') NOT NULL DEFAULT 'pending',
        `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS `bets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email_id` INT NOT NULL,
        `bet_data_json` JSON,
        `settlement_details` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo "Tables created successfully!\n";

} catch (PDOException $e) {
    die("Error creating tables: " . $e->getMessage() . "\n");
}
