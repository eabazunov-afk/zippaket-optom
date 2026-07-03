-- Настройки приложения (key-value). Редактируются из админки (admin/settings.php).
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(64)  NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Цены сырья калькулятора (₽/кг). Стартовые значения = текущие захардкоженные,
-- поэтому расчёт не меняется, пока их не отредактируют в админке.
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('calc_price_eva', '380'),
  ('calc_price_pvd', '360')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
