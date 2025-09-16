--
-- Table structure for table `users`
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Add user_id to chat_logs table
--
ALTER TABLE `chat_logs`
ADD COLUMN `user_id` INT(11) NULL AFTER `id`,
ADD INDEX `fk_user_id` (`user_id`);

-- Note: A foreign key constraint is not added here to allow for flexibility,
-- for example, if you want to keep logs even if a user is deleted,
-- or to allow for logs that are not associated with any user (e.g., from an initial anonymous upload).
-- If you want to enforce the relationship, you can add the following constraint:
--
-- ALTER TABLE `chat_logs`
-- ADD CONSTRAINT `fk_chatlogs_user`
-- FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
-- ON DELETE SET NULL ON UPDATE CASCADE;
