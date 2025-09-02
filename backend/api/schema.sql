-- Database schema for the Dou Dizhu game

-- The `rooms` table holds information about a game lobby.
-- A room is where players gather before starting a game.
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_code` varchar(255) NOT NULL UNIQUE,
  `state` enum('waiting','playing','finished') NOT NULL DEFAULT 'waiting',
  `current_game_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `room_players` table links users to rooms and stores their state within the room.
CREATE TABLE `room_players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` varchar(255) NOT NULL, -- This could be a session ID or a foreign key to a users table
  `seat` int(11) NOT NULL, -- Player's seat number (1, 2, or 3)
  `is_landlord` tinyint(1) NOT NULL DEFAULT 0,
  `hand_cards` json DEFAULT NULL, -- Player's current hand of cards
  `score` int(11) NOT NULL DEFAULT 0, -- Overall score for the player in this room
  `joined_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_player_seat` (`room_id`, `seat`),
  UNIQUE KEY `room_player_user` (`room_id`, `user_id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `games` table stores the state of a single round of Dou Dizhu.
-- A room can have many games.
CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `game_state` enum('bidding','playing','finished') NOT NULL DEFAULT 'bidding',
  `landlord_id` varchar(255) DEFAULT NULL, -- The user_id of the player who is the landlord
  `current_turn_player_id` varchar(255) DEFAULT NULL, -- The user_id of the player whose turn it is
  `bottom_cards` json NOT NULL, -- The 3 cards left aside for the landlord
  `current_bid` int(11) NOT NULL DEFAULT 0, -- The highest bid amount
  `bids_history` json DEFAULT NULL, -- Tracks bids to determine end of bidding
  `last_play_id` int(11) DEFAULT NULL, -- Foreign key to the `plays` table
  `winner_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The `plays` table stores a log of every card play made in a game.
CREATE TABLE `plays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `game_id` int(11) NOT NULL,
  `player_id` varchar(255) NOT NULL,
  `cards_played` json NOT NULL, -- The cards that were played
  `play_type` varchar(50) NOT NULL, -- e.g., 'single', 'pair', 'trio', 'bomb'
  `play_value` int(11) NOT NULL, -- A numeric value for the play to make comparisons easier
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
