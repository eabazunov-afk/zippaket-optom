-- Миграция: интернет-магазин (поля упаковки + заказы)
-- БД: c103264_zippaket_optom_ru, charset utf8mb4
--
-- Идемпотентна: повторный запуск ничего не меняет и не падает.
-- MySQL не поддерживает `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`,
-- поэтому наличие колонки проверяется через information_schema + PREPARE.

SET @db := DATABASE();

-- 1. Поля упаковки у товаров

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND COLUMN_NAME = 'min_order_qty') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD COLUMN `min_order_qty` INT NOT NULL DEFAULT 1 AFTER `unit`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND COLUMN_NAME = 'qty_step') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD COLUMN `qty_step` INT NOT NULL DEFAULT 1 AFTER `min_order_qty`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND COLUMN_NAME = 'pack_label') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD COLUMN `pack_label` VARCHAR(100) DEFAULT NULL AFTER `qty_step`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Заказы
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(32) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'new',
  `customer_type` ENUM('individual','company') NOT NULL DEFAULT 'individual',
  `customer_name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(32) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `company_name` VARCHAR(255) DEFAULT NULL,
  `inn` VARCHAR(12) DEFAULT NULL,
  `kpp` VARCHAR(9) DEFAULT NULL,
  `legal_address` VARCHAR(500) DEFAULT NULL,
  `needs_invoice` TINYINT(1) NOT NULL DEFAULT 0,
  `delivery_method` ENUM('pickup','courier','tk') NOT NULL DEFAULT 'pickup',
  `delivery_address` VARCHAR(500) DEFAULT NULL,
  `comment` TEXT,
  `payment_method` ENUM('online','invoice') NOT NULL DEFAULT 'online',
  `items_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_id` VARCHAR(64) DEFAULT NULL,
  `payment_status` VARCHAR(32) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Позиции заказа (snapshot цены/названия)
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `product_id` INT DEFAULT NULL,
  `name_snapshot` VARCHAR(500) NOT NULL,
  `price_snapshot` DECIMAL(15,2) NOT NULL,
  `qty` INT NOT NULL,
  `line_total` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
