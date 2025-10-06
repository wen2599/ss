-- Main Database Schema
-- This file contains the final, authoritative schema for the database.
-- It should be used to set up a new database from scratch.

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login_time` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `allowed_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_id` VARCHAR(255) UNIQUE,
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `from_address` VARCHAR(255),
    `subject` VARCHAR(255),
    `body_html` TEXT,
    `body_text` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `betting_slips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email_id` INT NOT NULL,
    `raw_text` VARCHAR(1000) NOT NULL,
    `parsed_data` JSON,
    `is_valid` BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
