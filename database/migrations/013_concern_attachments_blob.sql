-- 013: Store concern attachments (image / document bytes) in MySQL instead of the uploads/ folder.
-- A separate table keeps the binary data out of the many `SELECT c.*` queries on `concerns`.
-- One attachment per concern (same rule as before); deleting a concern deletes its file.
-- Existing files referenced by concerns.attachment_path are imported by database/migrate.php right
-- after this migration; that legacy column is then left NULL and no longer used.
CREATE TABLE IF NOT EXISTS `concern_attachments` (
  `attachment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `concern_id` INT NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `sha256` CHAR(64) NOT NULL,
  `file_data` LONGBLOB NOT NULL,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_attachment_concern` (`concern_id`),
  CONSTRAINT `fk_attachment_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
