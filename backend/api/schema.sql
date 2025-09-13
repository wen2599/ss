-- SQLite schema for the Mark Six Lottery System with User Authentication

-- The `users` table stores persistent player accounts.
CREATE TABLE `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `display_id` TEXT NOT NULL UNIQUE,
  `phone_number` TEXT NOT NULL UNIQUE,
  `password_hash` TEXT NOT NULL,
  `points` INTEGER NOT NULL DEFAULT 1000,
  `created_at` TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- The `lottery_draws` table holds information about each lottery draw.
CREATE TABLE `lottery_draws` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `draw_number` TEXT NOT NULL UNIQUE,
  `draw_date` TEXT NOT NULL,
  `winning_numbers` TEXT, -- Storing JSON array of numbers
  `status` TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open', 'closed', 'settled')),
  `created_at` TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  `updated_at` TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Trigger to automatically update `updated_at` timestamp on `lottery_draws` table update.
CREATE TRIGGER `lottery_draws_updated_at`
AFTER UPDATE ON `lottery_draws`
FOR EACH ROW
BEGIN
  UPDATE `lottery_draws` SET `updated_at` = (datetime('now','localtime')) WHERE `id` = OLD.id;
END;

-- The `bets` table stores the bets placed by users on a specific draw.
CREATE TABLE `bets` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `draw_id` INTEGER NOT NULL,
  `bet_type` TEXT NOT NULL, -- e.g., 'single', 'multiple', 'box'
  `bet_numbers` TEXT NOT NULL, -- Storing JSON array of numbers
  `bet_amount` INTEGER NOT NULL,
  `winnings` INTEGER DEFAULT 0,
  `status` TEXT NOT NULL DEFAULT 'placed' CHECK(status IN ('placed', 'settled', 'lost', 'won')),
  `created_at` TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`draw_id`) REFERENCES `lottery_draws` (`id`) ON DELETE CASCADE
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

-- The `chat_messages` table stores chat messages for each room.
-- The `chat_messages` table has been removed as it was part of the old game system.
