-- 008: Administrators manage categories and reply to student feedback

-- Inactive categories are hidden from the Submit form but stay on existing concerns
ALTER TABLE `categories`
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`,
  ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `is_active`;

ALTER TABLE `feedback`
  ADD COLUMN `admin_reply` TEXT NULL AFTER `rating`,
  ADD COLUMN `replied_by` INT NULL AFTER `admin_reply`,
  ADD COLUMN `replied_at` DATETIME NULL AFTER `replied_by`,
  ADD CONSTRAINT `fk_feedback_replied_by` FOREIGN KEY (`replied_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;
