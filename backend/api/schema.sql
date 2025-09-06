-- Database schema for the Thirteen (十三张) game

-- The `rooms` table holds information about a game lobby.
-- A room is where players gather before starting a game.
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` varchar(255) DEFAULT NULL UNIQUE, -- Telegram chat ID where the game is active
  `room_code` varchar(255) NOT NULL UNIQUE,
  `state` enum('waiting','playing','finished') NOT NULL DEFAULT 'waiting',
  `current_game_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `room_players` table links users to rooms and stores their state within the room.
-- It holds the 13 cards dealt to the player for the current game.
CREATE TABLE `room_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` varchar(255) NOT NULL, -- This could be a session ID or a foreign key to a users table
  `seat` int(11) NOT NULL, -- Player's seat number (1 to 4)
  `hand_cards` json DEFAULT NULL, -- Player's current 13 cards for the round
  `score` int(11) NOT NULL DEFAULT 0, -- Overall score for the player in this room across all games
  `joined_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_player_seat` (`room_id`, `seat`),
  UNIQUE KEY `room_player_user` (`room_id`, `user_id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `games` table stores the state of a single round of Thirteen.
-- A room can have many games.
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
-- This is the core table for the game's playing state.
CREATE TABLE `player_hands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `player_id` varchar(255) NOT NULL, -- Corresponds to user_id in room_players
  `is_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `is_valid` tinyint(1) DEFAULT NULL, -- NULL until submitted and validated, 1 for valid, 0 for invalid (scooped)
  `front_hand` json DEFAULT NULL, -- 3 cards
  `middle_hand` json DEFAULT NULL, -- 5 cards
  `back_hand` json DEFAULT NULL, -- 5 cards
  `front_hand_details` json DEFAULT NULL, -- e.g., {"type": "pair", "rank": "A", "value": 14}
  `middle_hand_details` json DEFAULT NULL, -- e.g., {"type": "full_house", "rank": "K", "value": 13}
  `back_hand_details` json DEFAULT NULL,
  `score_details` json DEFAULT NULL, -- To store breakdown of points won/lost against each player
  `round_score` int(11) NOT NULL DEFAULT 0, -- Total score for this player in this round
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_player` (`game_id`, `player_id`),
  FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
