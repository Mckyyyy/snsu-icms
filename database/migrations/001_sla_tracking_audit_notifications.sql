-- 001: SLA tracking, AI explainability, audit trail and notifications
-- Implements: IPO "Organized Database: structured records & audit trails",
--             Sequence step 4 "dispatches live notification to Student Dashboard",
--             Scope "SLA target assignment" (deadline + breach tracking).

-- SLA & lifecycle timestamps on concerns
ALTER TABLE `concerns`
  ADD COLUMN `sla_due_at` DATETIME NULL AFTER `sla_hours`,
  ADD COLUMN `first_response_at` DATETIME NULL AFTER `sla_due_at`,
  ADD COLUMN `resolved_at` DATETIME NULL AFTER `first_response_at`,
  ADD COLUMN `ai_reasons` TEXT NULL AFTER `ai_urgency_score`,
  ADD COLUMN `ai_suggested_category_id` INT NULL AFTER `ai_reasons`,
  ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD INDEX `idx_concerns_sla_due` (`sla_due_at`);

-- Backfill existing rows
UPDATE `concerns`
   SET `sla_due_at` = DATE_ADD(`date_submitted`, INTERVAL `sla_hours` HOUR)
 WHERE `sla_due_at` IS NULL;

UPDATE `concerns` c
   SET c.`first_response_at` = (SELECT MIN(r.`date_responded`) FROM `responses` r WHERE r.`concern_id` = c.`concern_id`)
 WHERE c.`first_response_at` IS NULL;

UPDATE `concerns` c
   SET c.`resolved_at` = COALESCE(
         (SELECT MAX(r.`date_responded`) FROM `responses` r WHERE r.`concern_id` = c.`concern_id` AND r.`status_id` = 3),
         c.`date_submitted`)
 WHERE c.`status_id` = 3 AND c.`resolved_at` IS NULL;

-- Audit trail for every state change (status, category, priority, creation)
CREATE TABLE IF NOT EXISTS `concern_audit_logs` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY,
  `concern_id` INT NOT NULL,
  `actor_type` ENUM('student', 'admin', 'system') NOT NULL,
  `actor_id` INT DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `old_value` VARCHAR(255) DEFAULT NULL,
  `new_value` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`concern_id`),
  CONSTRAINT `fk_audit_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- In-app notifications (replaces the hard-coded navbar items)
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_type` ENUM('student', 'admin') NOT NULL,
  `recipient_id` INT NOT NULL,
  `concern_id` INT DEFAULT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'info',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_recipient` (`recipient_type`, `recipient_id`, `is_read`),
  CONSTRAINT `fk_notif_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
