-- This is a template for the tables that will be created dynamically by the bot.
-- The placeholder `{TABLE_NAME}` should be replaced with the actual table name by the bot script.

CREATE TABLE IF NOT EXISTS `{TABLE_NAME}` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `column_a` TEXT,
    `column_b` TEXT,
    `column_c` TEXT,
    `column_d` TEXT,
    `column_e` TEXT,
    `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
