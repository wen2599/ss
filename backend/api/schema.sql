-- Database schema for the Thirteen game

-- The `rooms` table holds information about a game lobby.
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_code` varchar(255) NOT NULL UNIQUE,
  `state` enum('waiting','arranging','finished') NOT NULL DEFAULT 'waiting',
  `game_state` json DEFAULT NULL, -- Stores comparison results etc.
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `chat_id` varchar(255) DEFAULT NULL UNIQUE, -- Telegram chat ID where the game is active
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `room_players` table links users to rooms and stores their state within the room.
CREATE TABLE `room_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` varchar(255) NOT NULL, -- This could be a session ID or a foreign key to a users table
  `seat` int(11) NOT NULL, -- Player's seat number (1-4)
  `hand_cards` json DEFAULT NULL, -- Player's current 13 cards
  `front_hand` json DEFAULT NULL,
  `middle_hand` json DEFAULT NULL,
  `back_hand` json DEFAULT NULL,
  `hand_is_set` tinyint(1) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0, -- Overall score for the player in this room
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_player_seat` (`room_id`, `seat`),
  UNIQUE KEY `room_player_user` (`room_id`, `user_id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
