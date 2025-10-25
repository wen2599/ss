-- SQL Migration File
-- This file contains the schema for the application's database.

-- Create the `users` table
-- This table stores user credentials for authentication.
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `telegram_chat_id` VARCHAR(255) NULL, -- Added: Telegram chat ID for bot integration
    `telegram_username` VARCHAR(255) NULL, -- Added: Telegram username for bot integration
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optimize: Add a unique index to the username for faster lookup and uniqueness enforcement
CREATE UNIQUE INDEX idx_username ON users(username);
-- Optimize: Add index for telegram_chat_id if frequently queried
CREATE INDEX idx_telegram_chat_id ON users(telegram_chat_id);

-- Create the `emails` table
-- This table stores the content of emails received by the system.
CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL, -- Added: Link email to a user
    `sender` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `is_private` BOOLEAN NOT NULL DEFAULT 0, -- 0 for public, 1 for private
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id), -- Added: Index for foreign key
    CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- Added: Foreign key constraint
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optimize: Add an index to the is_private column for faster querying of public/private emails
CREATE INDEX idx_is_private ON emails(is_private);
-- Optimize: Add indexes for sender and subject if they are frequently used in search/filter operations
CREATE INDEX idx_sender ON emails(sender);
CREATE INDEX idx_subject ON emails(subject);

-- Create the `lottery_winners` table
-- This table stores the lottery winners.
CREATE TABLE IF NOT EXISTS `lottery_winners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `prize` VARCHAR(255) NOT NULL,
    `draw_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optimize: Add an index to draw_date for faster querying by date
CREATE INDEX idx_draw_date ON lottery_winners(draw_date);

-- Create the `lottery_results` table
-- This table stores the results of different lotteries.
CREATE TABLE IF NOT EXISTS `lottery_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lottery_type` VARCHAR(50) NOT NULL,
    `issue_number` VARCHAR(50) NOT NULL,
    `winning_numbers` VARCHAR(255) NOT NULL,
    `number_colors_json` TEXT,
    `draw_date` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `type_issue` (`lottery_type`, `issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Dummy Data for `lottery_results`
INSERT IGNORE INTO `lottery_results` (`lottery_type`, `issue_number`, `winning_numbers`, `number_colors_json`, `draw_date`)
VALUES
    ('老澳', '20240101', '01,02,03,04,05,06,07', '{"01":"red","02":"blue","03":"green","04":"red","05":"blue","06":"green","07":"red"}', '2024-01-01 21:30:00'),
    ('新澳', '20240101', '08,09,10,11,12,13,14', '{"08":"blue","09":"green","10":"red","11":"blue","12":"green","13":"red","14":"blue"}', '2024-01-01 22:30:00'),
    ('香港', '20240101', '15,16,17,18,19,20,21', '{"15":"green","16":"red","17":"blue","18":"green","19":"red","20":"blue","21":"green"}', '2024-01-01 23:30:00');
