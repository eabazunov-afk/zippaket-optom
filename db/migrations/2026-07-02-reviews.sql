-- Отзывы покупателей (с модерацией). Публичные показываем при is_approved=1.
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `author_name` VARCHAR(100) NOT NULL,
  `author_role` VARCHAR(120) DEFAULT NULL,        -- «оптовый покупатель», компания/сфера
  `rating` TINYINT NOT NULL DEFAULT 5,            -- 1..5
  `body` TEXT NOT NULL,
  `product_id` INT DEFAULT NULL,                  -- NULL = общий отзыв о компании
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0,    -- модерация
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_approved` (`is_approved`, `created_at`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Стартовое наполнение (одобренные) — заменяет захардкоженные заглушки главной.
INSERT INTO `reviews` (`author_name`,`author_role`,`rating`,`body`,`is_approved`) VALUES
('Алексей М.','оптовый покупатель',5,'Заказывали партию слайдеров под фасовку — приехало в срок, качество отличное. Менеджер пересчитал цену под наш объём.',1),
('ООО «Пример»','производство продуктов',5,'Берём грипперы регулярно. Удобно, что можно по счёту для юрлица. Цена на объём приятная.',1),
('Ирина К.','маркетплейс-селлер',5,'Нужна была упаковка с печатью — сделали образец бесплатно, потом тираж. Рекомендую.',1);
