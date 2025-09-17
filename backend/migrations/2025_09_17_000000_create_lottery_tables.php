<?php
// backend/migrations/2025_09_17_000000_create_lottery_tables.php

/**
 * Migration: Overhauls the database for the new lottery betting system.
 * - Drops the old chat_logs table.
 * - Creates tables: `bets`, `lottery_draws`, `lottery_rules`.
 * - Pre-populates the rules table with default values.
 */

// Drop the old table, ignore if it doesn't exist.
$pdo->exec("DROP TABLE IF EXISTS `chat_logs`;");

// 1. Create the `bets` table to store user wagers.
$sql_create_bets = "
CREATE TABLE `bets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `issue_number` varchar(255) DEFAULT NULL,
  `original_content` text NOT NULL,
  `bet_data` json NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'unsettled',
  `settlement_data` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `issue_number` (`issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$pdo->exec($sql_create_bets);

// 2. Create the `lottery_draws` table to store winning numbers.
$sql_create_draws = "
CREATE TABLE `lottery_draws` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lottery_name` varchar(255) NOT NULL,
  `issue_number` varchar(255) NOT NULL,
  `winning_numbers` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `lottery_issue` (`lottery_name`,`issue_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$pdo->exec($sql_create_draws);

// 3. Create the `lottery_rules` table for game data (Zodiacs, Colors, Odds).
$sql_create_rules = "
CREATE TABLE `lottery_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(255) NOT NULL,
  `rule_value` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rule_key` (`rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$pdo->exec($sql_create_rules);

// 4. Pre-populate the rules table with default game data.
$zodiac_json = '{"蛇":[1,13,25,37,49],"龙":[2,14,26,38],"兔":[3,15,27,39],"虎":[4,16,28,40],"牛":[5,17,29,41],"鼠":[6,18,30,42],"猪":[7,19,31,43],"狗":[8,20,32,44],"鸡":[9,21,33,45],"猴":[10,22,34,46],"羊":[11,23,35,47],"马":[12,24,36,48]}';
$color_json = '{"红波":[1,7,13,19,23,29,35,45,2,8,12,18,24,30,34,40,46],"蓝波":[3,9,15,25,31,37,41,47,4,10,14,20,26,36,42,48],"绿波":[5,11,17,21,27,33,39,43,49,6,16,22,28,32,38,44]}';
$odds_json = '{"special":47,"default":45}';

$sql_populate_rules = "
INSERT INTO `lottery_rules` (rule_key, rule_value) VALUES
('zodiac_mappings', :zodiac_json),
('color_mappings', :color_json),
('odds', :odds_json)
ON DUPLICATE KEY UPDATE rule_value = VALUES(rule_value);
";
$stmt = $pdo->prepare($sql_populate_rules);
$stmt->execute([
    ':zodiac_json' => $zodiac_json,
    ':color_json' => $color_json,
    ':odds_json' => $odds_json,
]);

// No statement needs to be returned as execution is handled internally.
?>
