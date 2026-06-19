-- IDOR fix: непредсказуемый токен доступа к заказу/счёту.
-- Применить на проде: mysql ... < этот файл
ALTER TABLE `orders`
    ADD COLUMN `access_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `order_number`,
    ADD KEY `idx_access_token` (`access_token`);

-- Бэкофилл токенов для уже существующих заказов (чтобы старые ссылки тоже защитить):
UPDATE `orders` SET `access_token` = SUBSTRING(SHA2(CONCAT(id, order_number, RAND(), UUID()), 256), 1, 32)
WHERE `access_token` IS NULL OR `access_token` = '';
