-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create temp_tokens table for email verification
CREATE TABLE IF NOT EXISTS `temp_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 1 HOUR)
);

-- Create authorized_emails table
CREATE TABLE IF NOT EXISTS `authorized_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `authorized_by_admin` VARCHAR(255), -- Store admin chat ID or username
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create emails table to store incoming emails
CREATE TABLE IF NOT EXISTS `emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `subject` TEXT,
  `body` LONGTEXT,
  `from_email` VARCHAR(255),
  `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `template` TEXT,
  `bets_json` JSON,
  `corrected_bets_json` JSON,
  `dialog_history` JSON,
  `settlement_json` JSON,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- Create lottery_results table
CREATE TABLE IF NOT EXISTS `lottery_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `period` VARCHAR(50) NOT NULL UNIQUE,
  `numbers` JSON NOT NULL, -- JSON array of winning numbers
  `special` INT NOT NULL,
  `draw_time` DATETIME NOT NULL
);
