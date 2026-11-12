-- 016: Student recovery codes
--   A one-time code shown when the student registers (or generates one on the Profile page).
--   With it the student can reset a forgotten password without email or an administrator.
--   Only a bcrypt hash is stored; each successful use replaces it with a new code.
ALTER TABLE `students`
  ADD COLUMN `recovery_code_hash` VARCHAR(255) NULL AFTER `password`,
  ADD COLUMN `recovery_code_set_at` DATETIME NULL AFTER `recovery_code_hash`;
