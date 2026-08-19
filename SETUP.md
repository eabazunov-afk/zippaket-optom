# Установка проекта на новой машине

Быстрый старт для локальной разработки сайта **zippaket-optom.ru** (ZLOCK).

## Требования

- **PHP 8.3** (проект тестировался на Laragon PHP 8.3.30)
- **MySQL 8.x** (прод — 8.4)
- **Composer** (для PHPUnit)
- **Git**

## 1. Клонирование

```bash
git clone https://github.com/eabazunov-afk/zippaket-optom.git
cd zippaket-optom
git checkout feature/light-design-system   # актуальная ветка работы
```

## 2. Конфигурация (config.php)

`www/includes/config.php` **не** в репозитории (там пароли/ключи). Создай его из примера:

```bash
cp www/includes/config.example.php www/includes/config.php
```

Затем впиши в `config.php` реальные значения. **Минимум для локального запуска:**

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` — доступы к локальной БД;
- **все `SELLER_*`** — иначе футер сайта падает с Fatal (SELLER_NAME, SELLER_INN, SELLER_OGRN, SELLER_KPP, SELLER_ADDRESS, SELLER_WORKHOURS, SELLER_PHONE, SELLER_EMAIL, SELLER_BANK, SELLER_ACCOUNT, SELLER_CORR, SELLER_BIK).

Остальное (RECAPTCHA, AMOCRM, YOOKASSA, FNS) для локальной разработки можно
оставить заглушками — оплата/CRM просто не будут работать.

## 3. База данных

Создай базу и залей данные. **Вариант А (быстрый, с данными)** — импорт полного дампа:

```bash
mysql -u root -p -e "CREATE DATABASE zippaket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p zippaket < c103264_zippaket_optom_ru.sql
```

**Вариант Б (чистая схема)** — схема + товары:

```bash
mysql -u root -p zippaket < db/seed/schema.sql
mysql -u root -p zippaket < db/seed/products-data.sql
```

### Миграции

После заливки базы накати миграции по порядку (идемпотентны — можно повторно):

```bash
for f in db/migrations/*.sql; do
  echo "applying $f"
  mysql -u root -p zippaket < "$f"
done
```

Список на текущий момент:
- `2026-06-18-orders-schema.sql` — таблицы заказов
- `2026-06-19-order-access-token.sql` — токен доступа к заказу
- `2026-07-02-reviews.sql` — отзывы
- `2026-07-03-settings.sql` — настройки калькулятора (цены материалов)

### Админ-пользователь

Аккаунты админки лежат в таблице `admins` и **не** передаются через git.
Если базу залил из дампа — там уже есть `admin` (пароль знаешь ты). Если нет —
заведи запись вручную (роль `admin` или `manager`, `password_hash` через
`password_hash('пароль', PASSWORD_DEFAULT)`).

## 4. Запуск дев-сервера

Встроенный PHP-сервер с локальным роутером (эмулирует правила .htaccess):

```bash
php -S 127.0.0.1:8077 -t www www/_dev_router.php
```

Открыть: http://127.0.0.1:8077/ · админка: http://127.0.0.1:8077/admin/

## 5. Тесты

```bash
cd www
composer install          # один раз, ставит PHPUnit
php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests
```

Ожидаемо: `OK (80 tests, ...)`.

---

## Что синхронизируется, а что нет

| Через git (есть на GitHub) | Только локально (настроить заново) |
|---|---|
| Весь код, шаблоны, CSS/JS | `config.php` (пароли/ключи) |
| Миграции и сиды БД | Сама база данных (данные) |
| Тесты, документация | Загруженные файлы в `www/uploads/` |
| `_dev_router.php` | Аккаунты админки |
