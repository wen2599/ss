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

-- Optional: Add an index to the is_private column for faster querying of public emails
-- CREATE INDEX idx_is_private ON emails(is_private);

-- Optional: Add a foreign key relationship if you want to link emails to users
-- ALTER TABLE emails ADD COLUMN user_id INT NULL;
-- ALTER TABLE emails ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

