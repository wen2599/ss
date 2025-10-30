-- This SQL script is designed to be run to set up or update database tables.
-- Using `IF NOT EXISTS` and modifying existing tables is handled carefully.

--
-- Table structure for table `users`
-- Note: We are making `password_hash` nullable to support passwordless logins.
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NULL, -- Changed to NULLABLE
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A separate ALTER statement for existing databases to make the column nullable.
ALTER TABLE `users` MODIFY `password_hash` VARCHAR(255) NULL;


--
-- Table structure for table `received_emails`
-- This table stores emails forwarded from the Cloudflare worker.
--
CREATE TABLE IF NOT EXISTS `received_emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `from_address` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) DEFAULT 'No Subject',
    `body` TEXT,
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--
-- Table structure for table `authorized_emails`
--
CREATE TABLE IF NOT EXISTS `authorized_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `authorized_by` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `email_templates`
--
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `template_content` TEXT NOT NULL,
  `original_email_id` INT,
  `created_by_ai` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_original_email_id` (`original_email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `system_settings`
--
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Seed the Gemini API Key setting if it doesn't exist.
--
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE_PLEASE_UPDATE_VIA_BOT');
