-- 017: Record which engine prioritized each concern
--   'gemini' = Google Gemini language model, 'rules' = built-in rule-based engine (fallback / offline)
ALTER TABLE `concerns`
  ADD COLUMN `ai_engine` VARCHAR(20) NULL AFTER `ai_reasons`;
