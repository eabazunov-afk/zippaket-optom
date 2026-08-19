-- Индексы каталога: products проседает с ростом числа товаров.
-- Применить: mysql -u USER -p БАЗА < этот файл
--
-- Идемпотентна: каждый индекс добавляется только если его ещё нет
-- (проверка через information_schema.STATISTICS + PREPARE, как в
-- 2026-06-19-order-access-token.sql). Схема НЕ меняется — только KEY.
--
-- ПОЧЕМУ СОСТАВНЫЕ, А НЕ ОДИНОЧНЫЕ
-- ---------------------------------
-- Все выборки каталога (includes/catalog_functions.php, index.php, sitemap.php)
-- имеют одинаковую форму: WHERE is_active = 1 [+ фильтр] ORDER BY <колонка> LIMIT N.
-- Одиночный индекс по любой из колонок сортировки здесь бесполезен: MySQL умеет
-- взять для одной таблицы ровно один индекс, и если он не начинается с is_active,
-- фильтр придётся проверять по строкам таблицы. Одиночный индекс по is_active
-- тоже мало что даёт: кардинальность 2, ~94 % строк активны — оптимизатор его
-- игнорирует в пользу full scan + filesort.
-- Составной (is_active, <колонка сортировки>) закрывает обе задачи разом:
-- ref-поиск по is_active = 1 и чтение уже отсортированного хвоста индекса, то есть
-- filesort исчезает, а LIMIT останавливает чтение на первых N строках.
--
-- Замерено на копии products, раздутой до 200 704 строк (188 587 активных),
-- прогретый буферный пул, SQL_NO_CACHE:
--   новинки  (ORDER BY created_at DESC LIMIT 4)  1.079 с -> 0.0009 с
--   по цене  (ORDER BY price_rub ASC  LIMIT 12)  1.094 с -> 0.0010 с
--   популярное (ORDER BY quantity_sold DESC)     6.557 с -> 0.0152 с (холодный прогон)
--   DISTINCT category (фасет фильтра)            6.435 с -> 0.3762 с (холодный прогон)
-- В планах ALL/filesort по 195 538 строкам заменяются на ref/range по индексу.
--
-- Цена вопроса: products пишется редко (импорт прайса, правки в админке),
-- читается на каждой странице — перекос в сторону чтения оправдан.

SET @db := DATABASE();

-- 1. idx_active (is_active)
--    В InnoDB вторичный индекс физически хранится как (is_active, id), поэтому
--    один этот индекс закрывает дефолтный листинг каталога
--    (WHERE is_active = 1 ORDER BY id DESC LIMIT ...) и обход sitemap.php
--    (SELECT id, updated_at ... ORDER BY id) без filesort.
--    Он же самый узкий, и COUNT(*) WHERE is_active = 1 идёт по нему как Using index.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND INDEX_NAME = 'idx_active') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD KEY `idx_active` (`is_active`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. idx_active_created (is_active, created_at)
--    Новинки: catalog_functions.php::getNewProducts()
--    WHERE is_active = 1 AND price_rub > 0 ORDER BY created_at DESC, id DESC LIMIT 4.
--    Хвост индекса — приписанный InnoDB первичный ключ, поэтому вторичный ключ
--    сортировки (id DESC) тоже попадает в порядок индекса.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND INDEX_NAME = 'idx_active_created') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD KEY `idx_active_created` (`is_active`, `created_at`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. idx_active_sold (is_active, quantity_sold, stock_quantity)
--    Хиты и сортировка «популярное»: catalog_functions.php::getPopularProducts()
--    WHERE is_active = 1 AND stock_quantity > 0
--    ORDER BY quantity_sold DESC, stock_quantity DESC LIMIT 8
--    и getProducts(sort=popular) ORDER BY quantity_sold DESC.
--    Третья колонка добавлена намеренно: без неё MySQL отдаёт порядок только по
--    quantity_sold и всё равно делает filesort из-за вторичного ключа сортировки
--    (проверено EXPLAIN: с двухколоночным вариантом Extra = Using filesort,
--    с трёхколоночным — Backward index scan). Заодно stock_quantity > 0
--    проверяется прямо в индексе.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND INDEX_NAME = 'idx_active_sold') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD KEY `idx_active_sold` (`is_active`, `quantity_sold`, `stock_quantity`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. idx_active_price (is_active, price_rub)
--    Сортировка по цене: getProducts(sort=price_asc|price_desc)
--    WHERE is_active = 1 ORDER BY price_rub ASC|DESC LIMIT ...
--    Он же обслуживает фильтр price_rub > 0 (новинки, витрина index.php).
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND INDEX_NAME = 'idx_active_price') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD KEY `idx_active_price` (`is_active`, `price_rub`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. idx_active_stock (is_active, stock_quantity)
--    Главная (index.php): WHERE is_active = 1 AND price_rub > 0
--    ORDER BY stock_quantity DESC — самый горячий запрос сайта, раньше был
--    full scan + filesort по всей таблице.
--    Плюс getSpecialOffers() (stock_quantity > 100000), getProductsByClass()
--    и фильтр «в наличии» (stock_quantity > 0).
--    Существующий idx_stock (stock_quantity) не начинается с is_active и потому
--    для этих запросов не выбирается; удалять его не стали — миграция только
--    добавляет индексы.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND INDEX_NAME = 'idx_active_stock') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD KEY `idx_active_stock` (`is_active`, `stock_quantity`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. idx_active_category (is_active, category)
--    Фасет фильтра: SELECT DISTINCT category FROM products WHERE is_active = 1
--    ORDER BY category (catalog_functions.php::getCategories(), sitemap.php).
--    Без него добавление индексов выше делало этот запрос ХУЖЕ: оптимизатор
--    переставал брать idx_category и уходил в Using temporary; Using filesort.
--    С ним план — ref + Using index (покрывающий), без temporary и filesort.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products'
                  AND INDEX_NAME = 'idx_active_category') > 0,
  'SET @skip := 1',
  'ALTER TABLE `products` ADD KEY `idx_active_category` (`is_active`, `category`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Пересчёт статистики: без него оптимизатор какое-то время будет игнорировать
-- новые индексы (кардинальность = NULL сразу после ALTER).
ANALYZE TABLE `products`;
