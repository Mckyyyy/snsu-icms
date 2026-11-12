-- 015: Records are archived, never deleted
--   Administrators archive announcements, categories, concerns, user accounts and masterlist entries.
--   Archived rows stay in the database (with who archived them and when), are hidden from the
--   everyday lists, and can be restored from each page's "Archived" tab.
--   Rejected self-registrations are kept in registration_rejections (the Pending account itself is
--   released so the real owner can still register with that student number / email).

ALTER TABLE `announcements`
  ADD COLUMN `archived_at` DATETIME NULL,
  ADD COLUMN `archived_by` INT NULL;

ALTER TABLE `categories`
  ADD COLUMN `archived_at` DATETIME NULL,
  ADD COLUMN `archived_by` INT NULL;

ALTER TABLE `concerns`
  ADD COLUMN `archived_at` DATETIME NULL,
  ADD COLUMN `archived_by` INT NULL,
  ADD KEY `idx_concerns_archived` (`archived_at`);

ALTER TABLE `students`
  ADD COLUMN `archived_at` DATETIME NULL,
  ADD COLUMN `archived_by` INT NULL;

ALTER TABLE `administrators`
  ADD COLUMN `archived_at` DATETIME NULL,
  ADD COLUMN `archived_by` INT NULL;

ALTER TABLE `student_masterlist`
  ADD COLUMN `archived_at` DATETIME NULL,
  ADD COLUMN `archived_by` INT NULL;

-- Hidden categories from before this migration count as archived
UPDATE `categories` SET `archived_at` = NOW() WHERE `is_active` = 0 AND `archived_at` IS NULL;

CREATE TABLE IF NOT EXISTS `registration_rejections` (
  `rejection_id` INT NOT NULL AUTO_INCREMENT,
  `student_no` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `department` VARCHAR(150) NULL,
  `program` VARCHAR(150) NULL,
  `registered_at` DATETIME NULL,
  `registration_note` VARCHAR(255) NULL,
  `reason` VARCHAR(500) NULL,
  `rejected_by` INT NULL,
  `rejected_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rejection_id`),
  KEY `idx_rejections_student_no` (`student_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
