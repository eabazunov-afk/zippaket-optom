-- IDOR fix: непредсказуемый токен доступа к заказу/счёту.
-- Применить: mysql -u USER -p БАЗА < этот файл
--
-- Идемпотентна: колонка и индекс добавляются только если их ещё нет,
-- бэкофилл трогает только записи без токена.

SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'orders'
                  AND COLUMN_NAME = 'access_token') > 0,
  'SET @skip := 1',
  'ALTER TABLE `orders` ADD COLUMN `access_token` VARCHAR(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `order_number`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'orders'
                  AND INDEX_NAME = 'idx_access_token') > 0,
  'SET @skip := 1',
  'ALTER TABLE `orders` ADD KEY `idx_access_token` (`access_token`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Бэкофилл токенов для уже существующих заказов (чтобы старые ссылки тоже защитить):
UPDATE `orders` SET `access_token` = SUBSTRING(SHA2(CONCAT(id, order_number, RAND(), UUID()), 256), 1, 32)
WHERE `access_token` IS NULL OR `access_token` = '';
