-- 010: Anonymous submission
-- The concern stays linked to the student (so they can track it, get notifications and rate it),
-- but administrator screens, exports, reports and alerts never show who sent it.
ALTER TABLE `concerns`
  ADD COLUMN `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0 AFTER `student_id`;
