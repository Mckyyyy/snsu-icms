-- 019: Profile photos for students and administrators.
-- Stored in the database (like concern attachments) so they survive re-uploads of the site files.
-- The photo is cropped to a square, resized and re-encoded as JPEG before saving.
CREATE TABLE IF NOT EXISTS `profile_photos` (
  `owner_type` ENUM('student','admin') NOT NULL,
  `owner_id` INT NOT NULL,
  `mime_type` VARCHAR(50) NOT NULL DEFAULT 'image/jpeg',
  `file_size` INT NOT NULL,
  `sha256` CHAR(64) NOT NULL,
  `photo_data` MEDIUMBLOB NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`owner_type`, `owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
