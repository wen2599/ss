-- Main user table for authentication
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for user authentication tokens
CREATE TABLE IF NOT EXISTS `tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for authorizing emails before registration
CREATE TABLE IF NOT EXISTS `authorized_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing received emails linked to a user
CREATE TABLE IF NOT EXISTS `user_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `from_sender` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `raw_email` LONGTEXT,
  `parsed_content` JSON,
  `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing lottery numbers parsed from Telegram
CREATE TABLE IF NOT EXISTS `lottery_numbers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lottery_type` VARCHAR(100) NOT NULL,
  `issue_number` VARCHAR(255) NOT NULL,
  `numbers` VARCHAR(255) NOT NULL,
  `source` VARCHAR(255) DEFAULT NULL,
  `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `type_issue` (`lottery_type`, `issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
