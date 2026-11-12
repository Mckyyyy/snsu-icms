-- 018: The AI's estimate of how long the fix will really take (within the SLA deadline)
--   ai_fix_hours: estimated hours from submission to resolution (NULL = rule-based engine, no estimate)
--   ai_fix_note:  what the fix involves, e.g. "Electrician shuts off power and replaces the wet outlet"
ALTER TABLE `concerns`
  ADD COLUMN `ai_fix_hours` SMALLINT NULL AFTER `ai_engine`,
  ADD COLUMN `ai_fix_note` VARCHAR(160) NULL AFTER `ai_fix_hours`;
