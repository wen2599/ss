-- =================================================================
--  Database Schema for the Application
-- =================================================================

--
-- Table structure for table `users`
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `telegram_id` BIGINT NULL UNIQUE COMMENT 'The user''s unique Telegram ID',
  `username` VARCHAR(255) NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending' COMMENT 'User status: pending, approved, denied',
  `email` VARCHAR(255) NULL UNIQUE,
  `password` VARCHAR(255) NULL,
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
-- Table structure for table `parsing_templates`
--
CREATE TABLE IF NOT EXISTS `parsing_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL COMMENT 'NULL for global templates, otherwise user-specific',
  `type` VARCHAR(50) NOT NULL COMMENT 'e.g., zodiac, number_list, multiplier',
  `pattern` VARCHAR(512) NOT NULL COMMENT 'The regex pattern',
  `priority` INT NOT NULL DEFAULT 100 COMMENT 'Execution priority, lower numbers run first',
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =================================================================
--  Default Data Inserts
-- =================================================================

--
-- Default application settings
--
INSERT INTO `application_settings` (`setting_name`, `setting_value`, `description`)
VALUES
  ('gemini_api_key', 'YOUR_GEMINI_API_KEY', 'The API key for the Gemini AI service used for parsing corrections.')
ON DUPLICATE KEY UPDATE `setting_name` = `setting_name`;

--
-- Default global parsing templates (fallback)
--
INSERT INTO `parsing_templates` (`user_id`, `type`, `pattern`, `priority`, `description`)
VALUES
  (NULL, 'zodiac', '/([\p{Han},，\s]+?)(?:数各|各数)\s*([\p{Han}\d]+)\s*[元块]?/u', 10, 'Parses zodiac bets like "龙虎数各十元"'),
  (NULL, 'number_list', '/([0-9.,，、\s-]+)各\s*(\d+)\s*(?:#|[元块])/u', 20, 'Parses number list bets like "01,02,03各10元"'),
  (NULL, 'multiplier', '/(\d+)\s*[xX×\*]\s*(\d+)\s*[元块]?/u', 30, 'Parses multiplier bets like "49x100元"')
ON DUPLICATE KEY UPDATE `pattern` = VALUES(`pattern`);

