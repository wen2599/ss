-- SQLite schema for the Thirteen (十三张) game with User Authentication

-- The `users` table stores persistent player accounts.
CREATE TABLE `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `display_id` TEXT NOT NULL UNIQUE,
  `phone_number` TEXT NOT NULL UNIQUE,
  `password_hash` TEXT NOT NULL,
  `points` INTEGER NOT NULL DEFAULT 1000,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- The `rooms` table holds information about a game lobby.
CREATE TABLE `rooms` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `game_mode` TEXT NOT NULL CHECK(game_mode IN ('normal_2','normal_5','double_2','double_5')),
  `room_code` TEXT NOT NULL UNIQUE,
  `state` TEXT NOT NULL DEFAULT 'waiting' CHECK(state IN ('waiting','playing','finished')),
  `current_game_id` INTEGER DEFAULT NULL,
  `created_at` TEXT NOT NULL,
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Trigger to automatically update `updated_at` timestamp on `rooms` table update.
CREATE TRIGGER `rooms_updated_at`
AFTER UPDATE ON `rooms`
FOR EACH ROW
BEGIN
  UPDATE `rooms` SET `updated_at` = (datetime('now','localtime')) WHERE `id` = OLD.id;
END;

-- The `room_players` table links users to rooms for the duration of a game session.
CREATE TABLE `room_players` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `room_id` INTEGER NOT NULL,
  `user_id` INTEGER NOT NULL, -- Foreign key to the users table
  `seat` INTEGER NOT NULL,
  `hand_cards` TEXT DEFAULT NULL, -- Storing JSON as TEXT
  `joined_at` TEXT NOT NULL,
  UNIQUE (`room_id`, `user_id`),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);

-- The `friends` table stores the friendship relationships between users.
CREATE TABLE `friends` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `friend_id` INTEGER NOT NULL,
  `status` TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'accepted', 'blocked')),
  `created_at` TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  UNIQUE (`user_id`, `friend_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);

-- The `games` table stores the state of a single round of Thirteen.
CREATE TABLE `games` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `room_id` INTEGER NOT NULL,
  `game_state` TEXT NOT NULL DEFAULT 'dealing' CHECK(game_state IN ('dealing','setting_hands','showdown','finished')),
  `created_at` TEXT NOT NULL,
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
);

-- Trigger to automatically update `updated_at` timestamp on `games` table update.
CREATE TRIGGER `games_updated_at`
AFTER UPDATE ON `games`
FOR EACH ROW
BEGIN
  UPDATE `games` SET `updated_at` = (datetime('now','localtime')) WHERE `id` = OLD.id;
END;

-- The `player_hands` table stores the arranged hands for each player in a specific game.
CREATE TABLE `player_hands` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `game_id` INTEGER NOT NULL,
  `player_id` INTEGER NOT NULL, -- Corresponds to user_id in users table
  `is_submitted` INTEGER NOT NULL DEFAULT 0, -- Using INTEGER for boolean
  `is_valid` INTEGER DEFAULT NULL, -- Using INTEGER for boolean
  `front_hand` TEXT DEFAULT NULL, -- Storing JSON as TEXT
  `middle_hand` TEXT DEFAULT NULL,
  `back_hand` TEXT DEFAULT NULL,
  `front_hand_details` TEXT DEFAULT NULL,
  `middle_hand_details` TEXT DEFAULT NULL,
  `back_hand_details` TEXT DEFAULT NULL,
  `score_details` TEXT DEFAULT NULL,
  `round_score` INTEGER NOT NULL DEFAULT 0,
  UNIQUE (`game_id`, `player_id`),
  FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`player_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);

-- The `chat_messages` table stores chat messages for each room.
CREATE TABLE `chat_messages` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `room_id` INTEGER NOT NULL,
  `user_id` INTEGER NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
