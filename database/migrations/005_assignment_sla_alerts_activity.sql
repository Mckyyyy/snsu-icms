-- 005: Ticket assignment, automatic SLA escalation, administrator activity log

-- Which administrator owns a ticket, and the last SLA alert level already sent
ALTER TABLE `concerns`
  ADD COLUMN `assigned_admin_id` INT NULL AFTER `status_id`,
  ADD COLUMN `sla_alert_level` ENUM('none', 'at_risk', 'breached') NOT NULL DEFAULT 'none' AFTER `sla_due_at`,
  ADD INDEX `idx_concerns_assigned` (`assigned_admin_id`),
  ADD CONSTRAINT `fk_concern_assigned_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;

-- Existing tickets: don't flood admins with alerts for history that is already overdue
UPDATE `concerns`
   SET `sla_alert_level` = CASE
         WHEN `status_id` = 3 THEN 'none'
         WHEN `sla_due_at` < NOW() THEN 'breached'
         WHEN TIMESTAMPDIFF(SECOND, NOW(), `sla_due_at`) <= `sla_hours` * 3600 * 0.25 THEN 'at_risk'
         ELSE 'none'
       END;

-- Who did what in the admin portal (user management, announcements, assignments)
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `activity_id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT DEFAULT NULL,
  `action` VARCHAR(60) NOT NULL,
  `target_type` VARCHAR(30) DEFAULT NULL,
  `target_id` INT DEFAULT NULL,
  `details` VARCHAR(500) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_activity_admin` (`admin_id`, `created_at`),
  INDEX `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
