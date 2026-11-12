-- 014: Messenger-style message actions: reply, forward, edit, unsend, react, delete for me.
-- Additive only (nullable columns + new tables), so existing code keeps working.
ALTER TABLE `messages`
  ADD COLUMN `reply_to_id` INT NULL AFTER `body`,
  ADD COLUMN `forwarded_from_id` INT NULL AFTER `reply_to_id`,
  ADD COLUMN `edited_at` DATETIME NULL AFTER `read_at`,
  ADD COLUMN `unsent_at` DATETIME NULL AFTER `edited_at`,
  ADD COLUMN `updated_at` DATETIME(3) NULL AFTER `unsent_at`,
  ADD KEY `idx_message_updated` (`conversation_id`, `updated_at`);

-- One reaction per person per message
CREATE TABLE IF NOT EXISTS `message_reactions` (
  `message_id` INT NOT NULL,
  `user_type` ENUM('student','admin') NOT NULL,
  `user_id` INT NOT NULL,
  `emoji` VARCHAR(16) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`, `user_type`, `user_id`),
  CONSTRAINT `fk_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`message_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Delete for me": the message stays for everyone else
CREATE TABLE IF NOT EXISTS `message_hidden` (
  `message_id` INT NOT NULL,
  `user_type` ENUM('student','admin') NOT NULL,
  `user_id` INT NOT NULL,
  `hidden_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`, `user_type`, `user_id`),
  CONSTRAINT `fk_hidden_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`message_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
