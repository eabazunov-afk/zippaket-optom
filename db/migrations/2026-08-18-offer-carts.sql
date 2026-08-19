-- «Сборная корзина» расчётов (action=submit_offer_cart в includes/api.php).
-- До этой миграции таблицы не существовало: API возвращал success:true, ничего не сохраняя.
CREATE TABLE IF NOT EXISTS `offer_carts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cart_id` VARCHAR(50) NOT NULL,                 -- публичный идентификатор CART-<ts>-<rand>
  `items` MEDIUMTEXT NOT NULL,                    -- позиции корзины, JSON (JSON_UNESCAPED_UNICODE)
  `total_items` INT NOT NULL DEFAULT 0,           -- суммарное количество единиц товара
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cart_id` (`cart_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
