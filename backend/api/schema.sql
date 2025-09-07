-- Database schema for the Thirteen (十三张) game with User Authentication

-- The `users` table stores persistent player accounts.
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `display_id` varchar(4) NOT NULL UNIQUE,
  `phone_number` varchar(20) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 1000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `rooms` table holds information about a game lobby.
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_code` varchar(255) NOT NULL UNIQUE,
  `state` enum('waiting','playing','finished') NOT NULL DEFAULT 'waiting',
  `current_game_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `room_players` table links users to rooms for the duration of a game session.
CREATE TABLE `room_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL, -- Foreign key to the users table
  `seat` int(11) NOT NULL,
  `hand_cards` json DEFAULT NULL,
  `joined_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_player_user` (`room_id`, `user_id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `games` table stores the state of a single round of Thirteen.
CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `game_state` enum('dealing','setting_hands','showdown','finished') NOT NULL DEFAULT 'dealing',
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `player_hands` table stores the arranged hands for each player in a specific game.
CREATE TABLE `player_hands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL, -- Corresponds to user_id in users table
  `is_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `is_valid` tinyint(1) DEFAULT NULL,
  `front_hand` json DEFAULT NULL,
  `middle_hand` json DEFAULT NULL,
  `back_hand` json DEFAULT NULL,
  `front_hand_details` json DEFAULT NULL,
  `middle_hand_details` json DEFAULT NULL,
  `back_hand_details` json DEFAULT NULL,
  `score_details` json DEFAULT NULL,
  `round_score` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_player` (`game_id`, `player_id`),
  FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`player_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
