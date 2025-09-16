<?php
// backend/migrations/2024_01_01_000001_create_users_table_and_update_chat_logs.php

/**
 * Migration: Creates the `users` table and adds the `user_id` column to `chat_logs`.
 *
 * The migration runner script provides the global `$pdo` variable.
 * Since there are multiple statements, we will execute them directly
 * instead of returning a single PDOStatement.
 */

$sql_create_users = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql_alter_chat_logs = "
ALTER TABLE `chat_logs`
ADD COLUMN `user_id` INT(11) NULL AFTER `id`,
ADD INDEX `fk_user_id` (`user_id`);
";

// The runner script is set up to handle exceptions.
$pdo->exec($sql_create_users);
$pdo->exec($sql_alter_chat_logs);

// The runner script checks if a statement is returned. If not, it assumes
// execution was handled internally, so we don't need to return anything here.
?>
