-- This SQL script creates the table for storing parsed lottery results.
CREATE TABLE IF NOT EXISTS `lottery_results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lottery_name` VARCHAR(255) NOT NULL,
  `issue_number` VARCHAR(255) NOT NULL,
  `numbers` VARCHAR(255) NOT NULL COMMENT 'Comma-separated winning numbers',
  `parsed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_result` (`lottery_name`, `issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
