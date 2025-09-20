<?php
// backend/migrations/2024_01_01_000002_create_lottery_tables.php

/**
 * Migration: Creates the `lottery_rules`, `lottery_draws`, and `bets` tables.
 *
 * The migration runner script provides the global `$pdo` variable.
 */

$sql_lottery_rules = "
CREATE TABLE IF NOT EXISTS `lottery_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `numbers_drawn` int(11) NOT NULL,
  `total_numbers` int(11) NOT NULL,
  `draw_days` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql_lottery_draws = "
CREATE TABLE IF NOT EXISTS `lottery_draws` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_id` int(11) NOT NULL,
  `draw_date` date NOT NULL,
  `winning_numbers` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rule_id` (`rule_id`),
  CONSTRAINT `fk_draws_rule_id` FOREIGN KEY (`rule_id`) REFERENCES `lottery_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql_bets = "
CREATE TABLE IF NOT EXISTS `bets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `chat_log_id` int(11) DEFAULT NULL,
  `draw_id` int(11) DEFAULT NULL,
  `bet_data` json NOT NULL,
  `is_settled` tinyint(1) NOT NULL DEFAULT 0,
  `winnings` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `chat_log_id` (`chat_log_id`),
  KEY `draw_id` (`draw_id`),
  CONSTRAINT `fk_bets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bets_chat_log_id` FOREIGN KEY (`chat_log_id`) REFERENCES `chat_logs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bets_draw_id` FOREIGN KEY (`draw_id`) REFERENCES `lottery_draws` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Execute the statements
$pdo->exec($sql_lottery_rules);
$pdo->exec($sql_lottery_draws);
$pdo->exec($sql_bets);
?>
