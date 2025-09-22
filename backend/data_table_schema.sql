-- =================================================================
--  Database Schema for the Application
-- =================================================================

--
-- Table structure for table `users`
-- This table stores user information for the web application.
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =================================================================


--
-- Table structure for table `lottery_results`
-- This table stores parsed lottery results from the Telegram bot.
--

CREATE TABLE IF NOT EXISTS `lottery_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lottery_name` VARCHAR(255) NOT NULL,
  `issue_number` VARCHAR(255) NOT NULL,
  `numbers` VARCHAR(255) NOT NULL COMMENT 'Comma-separated winning numbers',
  `parsed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_result` (`lottery_name`, `issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
