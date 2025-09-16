<?php
// backend/migrations/2024_01_01_000000_create_initial_chat_logs_table.php

/**
 * Migration: Creates the initial chat_logs table.
 *
 * The migration runner script provides the global `$pdo` variable.
 * This script is expected to return a PDOStatement object that the runner will execute.
 */

$sql = "
CREATE TABLE IF NOT EXISTS `chat_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `parsed_data` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// The runner script will catch exceptions and execute the statement.
return $pdo->prepare($sql);
?>
