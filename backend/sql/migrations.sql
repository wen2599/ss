-- Database Migrations
-- This file contains a historical record of changes made to the database schema.
-- These are typically one-off commands that were run to upgrade the database.

-- Migration 1: Delete the obsolete `lottery_numbers` table.
DROP TABLE IF EXISTS `lottery_numbers`;

-- Migration 2: Add the `last_login_time` column to the `users` table.
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_login_time` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`;

-- Note: `ADD COLUMN IF NOT EXISTS` is a common pattern, but it's not standard SQL and may not work in all MySQL/MariaDB versions.
-- The setup script should ideally check for the column's existence before running this.

