-- 012: Real-time messaging between administrators and students
-- One conversation per student (shared admin inbox: any administrator can read and reply).
CREATE TABLE IF NOT EXISTS `conversations` (
  `conversation_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `last_message_id` INT NULL,
  `last_message_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_conversation_student` (`student_id`),
  KEY `idx_conversation_last` (`last_message_at`),
  CONSTRAINT `fk_conversation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `messages` (
  `message_id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `sender_type` ENUM('student','admin') NOT NULL,
  `sender_id` INT NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `read_at` DATETIME NULL,
  KEY `idx_message_conversation` (`conversation_id`, `message_id`),
  KEY `idx_message_unread` (`sender_type`, `read_at`, `conversation_id`),
  CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Online/offline presence and "is typing" signals (short-lived, refreshed by the browser)
CREATE TABLE IF NOT EXISTS `chat_presence` (
  `user_type` ENUM('student','admin') NOT NULL,
  `user_id` INT NOT NULL,
  `last_seen_at` DATETIME NULL,
  `typing_conversation_id` INT NULL,
  `typing_at` DATETIME(3) NULL,
  PRIMARY KEY (`user_type`, `user_id`),
  KEY `idx_presence_typing` (`typing_conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
