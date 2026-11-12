-- 006: Students can withdraw their own concern while it is still Pending.
-- The record is kept (status "Withdrawn") so nothing disappears from the audit trail or reports.

INSERT INTO `statuses` (`status_id`, `status_name`) VALUES (4, 'Withdrawn')
ON DUPLICATE KEY UPDATE `status_name` = VALUES(`status_name`);

ALTER TABLE `concerns`
  ADD COLUMN `withdrawn_at` DATETIME NULL AFTER `last_reopened_at`,
  ADD COLUMN `withdraw_reason` VARCHAR(255) NULL AFTER `withdrawn_at`;
