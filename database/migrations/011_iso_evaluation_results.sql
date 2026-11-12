-- 011: ISO/IEC 25010 survey results entered by administrators after the evaluation
-- (replaces fixed numbers on the Reports page; nothing is shown until real results are entered)
CREATE TABLE IF NOT EXISTS `iso_evaluation_results` (
  `characteristic` VARCHAR(40) NOT NULL,
  `mean_score` DECIMAL(3,2) NOT NULL,
  `respondents` INT NOT NULL,
  `survey_date` DATE NULL,
  `updated_by` INT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`characteristic`),
  CONSTRAINT `fk_iso_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `administrators` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
