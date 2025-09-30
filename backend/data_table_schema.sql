-- =================================================================
--  Database Schema for the Application
-- =================================================================

--
-- Table structure for table `users`
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `winning_rate` DECIMAL(5, 2) NOT NULL DEFAULT 45.00 COMMENT 'The user-specific winning rate, e.g., 45.00 or 47.00',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `lottery_results`
--
CREATE TABLE IF NOT EXISTS `lottery_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lottery_name` VARCHAR(255) NOT NULL,
  `issue_number` VARCHAR(255) NOT NULL,
  `numbers` VARCHAR(255) NOT NULL COMMENT 'Comma-separated winning numbers',
  `parsed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_result` (`lottery_name`, `issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `bills`
--
CREATE TABLE IF NOT EXISTS `bills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `raw_content` LONGTEXT NOT NULL,
  `settlement_details` LONGTEXT NULL,
  `total_cost` DECIMAL(10, 2) NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'unrecognized',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `application_settings`
--
CREATE TABLE IF NOT EXISTS `application_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_name` VARCHAR(255) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `description` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Default application settings
--
INSERT INTO `application_settings` (`setting_name`, `setting_value`, `description`)
VALUES
  ('gemini_api_key', 'YOUR_GEMINI_API_KEY', 'The API key for the Gemini AI service used for parsing corrections.')
ON DUPLICATE KEY UPDATE `setting_name` = `setting_name`; -- Do nothing if the key already exists

--
-- Table structure for table `parsing_templates`
--
CREATE TABLE IF NOT EXISTS `parsing_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL COMMENT 'NULL for global templates, or a user_id for user-specific ones',
  `pattern` VARCHAR(1024) NOT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'e.g., "zodiac", "number_list" to know how to process the match',
  `description` TEXT NULL COMMENT 'A description of what the pattern is for, can be AI-generated',
  `priority` INT NOT NULL DEFAULT 100 COMMENT 'Order in which to try the pattern (lower is higher priority)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_pattern` (`user_id`, `pattern`(255)),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;