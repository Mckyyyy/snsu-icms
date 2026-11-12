-- 009: Verified student registration
--   A. Self-registered accounts not found on the masterlist wait for administrator approval ('Pending').
--   D. Administrators import the Registrar's list of enrolled students (student_masterlist);
--      a registration whose student number and name match the list is approved automatically.

ALTER TABLE `students`
  MODIFY `status` ENUM('Active','Inactive','Pending') DEFAULT 'Active',
  ADD COLUMN `registration_source` ENUM('self','admin') NOT NULL DEFAULT 'admin' AFTER `status`,
  ADD COLUMN `masterlist_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `registration_source`,
  ADD COLUMN `registration_note` VARCHAR(255) NULL AFTER `masterlist_verified`,
  ADD COLUMN `approved_by` INT NULL AFTER `registration_note`,
  ADD COLUMN `approved_at` DATETIME NULL AFTER `approved_by`,
  ADD CONSTRAINT `fk_students_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `student_masterlist` (
  `masterlist_id` INT NOT NULL AUTO_INCREMENT,
  `student_no` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NULL,
  `department` VARCHAR(150) NULL,
  `program` VARCHAR(150) NULL,
  `imported_by` INT NULL,
  `imported_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`masterlist_id`),
  UNIQUE KEY `uq_masterlist_student_no` (`student_no`),
  CONSTRAINT `fk_masterlist_imported_by` FOREIGN KEY (`imported_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
