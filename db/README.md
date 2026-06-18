# Миграции БД

Применять по порядку имён файлов в `migrations/`.

## Применение

Локально/на сервере (MySQL/MariaDB):

```bash
mysql -u <user> -p <db_name> < db/migrations/2026-06-18-orders-schema.sql
```

Или через phpMyAdmin: импорт SQL-файла.

## Откат (если потребуется)

```sql
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
ALTER TABLE `products`
  DROP COLUMN `min_order_qty`,
  DROP COLUMN `qty_step`,
  DROP COLUMN `pack_label`;
```
