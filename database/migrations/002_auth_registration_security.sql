-- 002: Authentication & roles hardening (Phase 2)
-- Implements: Use case "Register / Login", password recovery, brute-force protection.

-- Force a password change after an admin-issued temporary password
ALTER TABLE `students`
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`,
  ADD COLUMN `last_login_at` DATETIME NULL AFTER `status`;

ALTER TABLE `administrators`
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`,
  ADD COLUMN `last_login_at` DATETIME NULL AFTER `status`;

-- Failed login attempts (rate limiting / lockout)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id` INT AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(150) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_identifier` (`identifier`, `attempted_at`),
  INDEX `idx_login_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset requests (admin-assisted; no SMTP on XAMPP)
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_type` ENUM('student', 'admin') NOT NULL,
  `user_id` INT NOT NULL,
  `status` ENUM('Pending', 'Completed', 'Dismissed') NOT NULL DEFAULT 'Pending',
  `requested_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `handled_by` INT DEFAULT NULL,
  `handled_at` DATETIME DEFAULT NULL,
  INDEX `idx_reset_user` (`user_type`, `user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
