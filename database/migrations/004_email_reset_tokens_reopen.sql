-- 004: Email notifications, self-service password reset links, ticket reopening

-- Outgoing email queue (sent by includes/mailer.php or scripts/process_email_queue.php)
CREATE TABLE IF NOT EXISTS `email_outbox` (
  `email_id` INT AUTO_INCREMENT PRIMARY KEY,
  `to_email` VARCHAR(150) NOT NULL,
  `to_name` VARCHAR(150) DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_text` TEXT NOT NULL,
  `body_html` MEDIUMTEXT DEFAULT NULL,
  `status` ENUM('Pending', 'Sent', 'Failed') NOT NULL DEFAULT 'Pending',
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `last_error` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME DEFAULT NULL,
  INDEX `idx_outbox_status` (`status`, `attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-time password reset links (only the SHA-256 hash of the token is stored)
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `token_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_type` ENUM('student', 'admin') NOT NULL,
  `user_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_reset_token_user` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Student can reopen a resolved ticket they are not satisfied with
ALTER TABLE `concerns`
  ADD COLUMN `reopen_count` TINYINT NOT NULL DEFAULT 0 AFTER `resolved_at`,
  ADD COLUMN `last_reopened_at` DATETIME NULL AFTER `reopen_count`;
