-- 003: Link feedback to a resolved concern (resolution satisfaction rating)
-- Implements: IPO Input "Feedback Data: Ratings", Output "Analytics & Reports: feedback summaries",
--             related literature (Creer 2022) "track complaint resolution and rate satisfaction".

ALTER TABLE `feedback`
  ADD COLUMN `concern_id` INT NULL AFTER `student_id`,
  ADD UNIQUE KEY `uq_feedback_concern` (`concern_id`),
  ADD CONSTRAINT `fk_feedback_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE;

-- Report queries filter and group on these
ALTER TABLE `concerns`
  ADD INDEX `idx_concerns_date_submitted` (`date_submitted`),
  ADD INDEX `idx_concerns_resolved_at` (`resolved_at`);
