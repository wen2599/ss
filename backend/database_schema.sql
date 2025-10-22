CREATE DATABASE IF NOT EXISTS `email_processor_db`;

USE `email_processor_db`;

CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `from_address` VARCHAR(255) NOT NULL,
    `to_address` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `raw_content` LONGTEXT NOT NULL, -- 存储原始邮件的完整内容
    `html_content` LONGTEXT DEFAULT NULL, -- 存储提取的 HTML 部分
    `ai_parsed_json` JSON DEFAULT NULL, -- 存储 AI 解析后的结构化 JSON 数据
    `worker_secret_provided` BOOLEAN DEFAULT FALSE, -- 标记 Worker 是否提供了 secret
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `service_name` VARCHAR(255) NOT NULL UNIQUE,
    `api_key` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lottery_numbers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numbers` JSON NOT NULL,
    `draw_time` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
