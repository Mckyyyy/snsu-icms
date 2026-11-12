-- 007: Students can edit a concern while it is still Pending and has no administrator response.
ALTER TABLE `concerns`
  ADD COLUMN `edited_at` DATETIME NULL AFTER `withdraw_reason`,
  ADD COLUMN `edit_count` TINYINT NOT NULL DEFAULT 0 AFTER `edited_at`;
