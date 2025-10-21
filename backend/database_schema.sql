CREATE DATABASE IF NOT EXISTS `email_processor_db`;

USE `email_processor_db`;

CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `from_address` VARCHAR(255) NOT NULL,
    `to_address` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `raw_content` LONGTEXT NOT NULL, -- 存储原始邮件的完整内容
    `html_content` LONGTEXT DEFAULT NULL, -- 存储提取的 HTML 部分
    `parsed_data` JSON DEFAULT NULL, -- 存储 AI 解析后的结构化 JSON 数据
    `worker_secret_provided` BOOLEAN DEFAULT FALSE, -- 标记 Worker 是否提供了 secret
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
