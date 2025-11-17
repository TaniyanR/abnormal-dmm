-- db/migration_api_settings.sql
-- Migration to add api_settings table for admin UI

USE `video_store`;

CREATE TABLE IF NOT EXISTS `api_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `api_settings` (`setting_key`, `setting_value`) VALUES
  ('API_RUN_INTERVAL', '3600'),
  ('API_FETCH_COUNT', '20'),
  ('API_FETCH_TOTAL', '100'),
  ('API_SORT', 'date'),
  ('API_GTE_DATE', ''),
  ('API_LTE_DATE', ''),
  ('API_SITE', 'FANZA'),
  ('API_SERVICE', 'digital'),
  ('API_FLOOR', 'videoa')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
