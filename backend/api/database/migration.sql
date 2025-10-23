
-- SQL Migration File
-- This file contains the schema for the application's database.

-- Create the `users` table
-- This table stores user credentials for authentication.
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the `emails` table
-- This table stores the content of emails received by the system.
CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `is_private` BOOLEAN NOT NULL DEFAULT 0, -- 0 for public, 1 for private
    `received_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the `lottery_winners` table
-- This table stores the lottery winners.
CREATE TABLE IF NOT EXISTS `lottery_winners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `prize` VARCHAR(255) NOT NULL,
    `draw_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Dummy Data for `lottery_winners` (only if not already present)
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


-- Optional: Add an index to the is_private column for faster querying of public emails
-- CREATE INDEX idx_is_private ON emails(is_private);

-- Optional: Add a foreign key relationship if you want to link emails to users
-- ALTER TABLE emails ADD COLUMN user_id INT NULL;
-- ALTER TABLE emails ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

