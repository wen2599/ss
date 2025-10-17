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

-- Table for authorizing emails before registration (currently unused but kept for potential future use)
CREATE TABLE IF NOT EXISTS `authorized_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing received emails
CREATE TABLE IF NOT EXISTS `emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `sender` VARCHAR(255) NOT NULL,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255),
  `html_content` LONGTEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Columns for AI-extracted data
  `vendor_name` VARCHAR(255) DEFAULT NULL,
  `bill_amount` DECIMAL(10, 2) DEFAULT NULL,
  `currency` VARCHAR(10) DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `invoice_number` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `is_processed` BOOLEAN NOT NULL DEFAULT FALSE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing lottery results
CREATE TABLE IF NOT EXISTS `lottery_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lottery_type` VARCHAR(100) NOT NULL,
  `issue_number` VARCHAR(255) NOT NULL,
  `winning_numbers` VARCHAR(255) NOT NULL,
  `zodiac_signs` VARCHAR(255) NOT NULL,
  `colors` VARCHAR(255) NOT NULL,
  `drawing_date` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `type_issue` (`lottery_type`, `issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
