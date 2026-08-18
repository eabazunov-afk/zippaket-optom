# База данных

```
db/
  seed/
    schema.sql         — структура всех таблиц (без данных), PII-free
    products-data.sql  — каталог: 49 товаров, без персональных данных
  migrations/
    ГГГГ-ММ-ДД-*.sql   — миграции, применяются по порядку имён файлов
```

## Установка с нуля

Порядок ровно такой (он проверен прогоном на чистой БД):

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS c103264_zippaket_optom_ru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root c103264_zippaket_optom_ru < db/seed/schema.sql
mysql -u root c103264_zippaket_optom_ru < db/seed/products-data.sql
# затем миграции по порядку (см. ниже)
```

PowerShell (Windows, MySQL из Laragon не в PATH):

```powershell
$mysql = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
$db    = "c103264_zippaket_optom_ru"
& $mysql -u root -e "CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"$mysql`" -u root $db < db\seed\schema.sql"
cmd /c "`"$mysql`" -u root $db < db\seed\products-data.sql"
```

- `schema.sql` — снимок актуальной схемы рабочей БД. Все `CREATE TABLE IF NOT EXISTS`,
  повторный запуск безопасен.
- `products-data.sql` — стартовый каталог. Колонки в `INSERT` перечислены явно
  (`--complete-insert`), поэтому будущие `ADD COLUMN ... AFTER` не сдвинут значения.
  **Не идемпотентен**: заливать в пустую таблицу `products`, повторный запуск
  упадёт на дубликате первичного ключа.

## Миграции

Применять по порядку имён файлов. Все миграции **идемпотентны** — повторный
запуск не падает и не плодит дубликаты (проверено двойным прогоном).
На свежем сиде они лишь досыпают стартовые строки: отзывы, цены калькулятора,
роль `superadmin`.

PowerShell (Windows, как на дев-машине):

```powershell
$mysql = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
$db    = "c103264_zippaket_optom_ru"
Get-ChildItem db\migrations\*.sql | Sort-Object Name | ForEach-Object {
    Write-Host "applying $($_.Name)"
    cmd /c "`"$mysql`" -u root $db < `"$($_.FullName)`""
}
```

> Почему `cmd /c`, а не `mysql -e "source ..."`: путь к репозиторию содержит
> кириллицу (`…\Documents\Сайт\…`), и команда `source` внутри mysql такой путь
> не открывает (`Failed to open file … error: 2`). Перенаправление через `cmd`
> отдаёт файл побайтово и работает.

Bash (Linux-хостинг):

```bash
for f in db/migrations/*.sql; do
  echo "applying $f"
  mysql -u USER -p БАЗА < "$f"
done
```

Или через phpMyAdmin: импорт SQL-файлов по одному, в порядке имён.

### Что делает каждая миграция

| Файл | Что добавляет |
|---|---|
| `2026-06-18-orders-schema.sql` | колонки упаковки у `products` (`min_order_qty`, `qty_step`, `pack_label`) + таблицы `orders`, `order_items` |
| `2026-06-19-order-access-token.sql` | `orders.access_token` + индекс (IDOR-фикс ссылок на заказ/счёт), бэкофилл токенов |
| `2026-07-02-reviews.sql` | таблица `reviews` (отзывы с модерацией) + 3 стартовых отзыва |
| `2026-07-03-settings.sql` | таблица `settings` (key-value) + цены материалов калькулятора |
| `2026-08-18-admin-roles.sql` | `admins.role` → `enum('superadmin','admin','manager','viewer')`, подъём старейшего админа до `superadmin` |
| `2026-08-18-offer-carts.sql` | таблица `offer_carts` («сборная корзина» расчётов из `includes/api.php`) |

> Обязательны все. Без `2026-07-02-reviews.sql` падает блок отзывов на главной,
> `admin/reviews.php` и `review_add.php`; без `2026-07-03-settings.sql` —
> `includes/app_settings.php` и `admin/settings.php`.

## Откат

Полный откат всех миграций (обратный порядок). **Уничтожает данные** —
делать только на локальной/тестовой базе.

```sql
-- 2026-08-18-offer-carts
DROP TABLE IF EXISTS `offer_carts`;

-- 2026-08-18-admin-roles (роли superadmin/viewer станут недопустимы;
-- сначала перевести таких пользователей в 'admin'/'manager')
UPDATE `admins` SET `role` = 'admin'   WHERE `role` = 'superadmin';
UPDATE `admins` SET `role` = 'manager' WHERE `role` = 'viewer';
ALTER TABLE `admins`
  MODIFY COLUMN `role` ENUM('admin','manager')
  COLLATE utf8mb4_unicode_ci DEFAULT 'manager';

-- 2026-07-03-settings
DROP TABLE IF EXISTS `settings`;

-- 2026-07-02-reviews
DROP TABLE IF EXISTS `reviews`;

-- 2026-06-19-order-access-token
ALTER TABLE `orders` DROP INDEX `idx_access_token`;
ALTER TABLE `orders` DROP COLUMN `access_token`;

-- 2026-06-18-orders-schema
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
ALTER TABLE `products`
  DROP COLUMN `min_order_qty`,
  DROP COLUMN `qty_step`,
  DROP COLUMN `pack_label`;
```

## Как пересобрать сид

Когда схема изменилась и сид отстал:

```bash
MYSQLDUMP="C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe"

# структура
"$MYSQLDUMP" -u root --no-data --routines=false --triggers=false \
  --skip-comments --no-tablespaces c103264_zippaket_optom_ru > db/seed/schema.sql
# затем вручную: убрать DROP TABLE, заменить CREATE TABLE -> CREATE TABLE IF NOT EXISTS,
# убрать AUTO_INCREMENT=N, вернуть шапку-комментарий файла

# каталог товаров
"$MYSQLDUMP" -u root --no-create-info --complete-insert --skip-extended-insert \
  --skip-comments --no-tablespaces c103264_zippaket_optom_ru products > db/seed/products-data.sql
```

⚠️ Дампить **только** `products`. В `leads`, `calculations`, `visits`, `orders`,
`admins`, `telegram_*` лежат персональные данные — они в репозиторий не попадают
(`.gitignore` режет `*.sql`, исключения — только `db/migrations/*` и `db/seed/*`).

Проверка после пересборки: залить `schema.sql` + `products-data.sql` + все миграции
во временную БД и сравнить `SHOW TABLES` с рабочей.
