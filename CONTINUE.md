# Как продолжить работу на другом компьютере

Репозиторий: `https://github.com/eabazunov-afk/zippaket-optom` (приватный).
Весь код, спеки, планы и PII-free сид БД — в GitHub. Локальные секреты и дамп с
персональными данными в репозиторий НЕ попадают (см. раздел «Что не в GitHub»).

## Текущий статус (на 2026-06-18)

Сделано (смержено в `master`):
- **План 1 — Фундамент и безопасность**: тест-харнес PHPUnit, валидация количеств,
  статусы заказа, интерфейс `PaymentGateway`, `config.example.php`, миграция БД.
- **План 2 — Каталог и карточка товара**: `getProductById`, страница `product.php`,
  ЧПУ `/product/<id>`, JSON-LD, подпись упаковки.
- **План 3 — Корзина и checkout**: серверная сессионная корзина, страница корзины,
  оформление (физ/юр, доставка, способ оплаты, CSRF), создание заказа
  (`orders`/`order_items`, snapshot), счётчик корзины.
- **План 4 — Оплата ЮKassa**: `YooKassaGateway` (контракт `PaymentGateway`), создание
  платежа из checkout с redirect на форму ЮKassa, webhook `api/payment_callback.php`
  (verifySignature по IP + повторный getPayment), переход заказа `pending_payment→paid`.
  Код готов; нужны креды тестового магазина ЮKassa в `config.php` + настройка webhook
  в ЛК. См. `docs/superpowers/plans/2026-06-18-оплата-юкасса.md`.

Дальше по дорожной карте:
- **План 5 — Уведомления** (заказ → amoCRM/Telegram/email; токен amoCRM истёк 31.05.2026 — перевыпустить).
- **План 6 — Premium-редизайн + SEO**.

Артефакты: спека — `docs/superpowers/specs/`, планы — `docs/superpowers/plans/`,
research — `thoughts/research/`.

## Установка на новой машине

### 1. Клонировать репозиторий
```bash
git clone https://github.com/eabazunov-afk/zippaket-optom.git
cd zippaket-optom
```

### 2. PHP + MySQL
Установить локальную среду (рекомендуется **Laragon**, как на исходной машине):
PHP 8.3+, MySQL/MariaDB 8.x, Composer. Запустить MySQL.

### 3. Зависимости Composer
```bash
cd www
php composer.phar install      # или: composer install (если composer в PATH)
```
(в репозитории лежит `www/composer` — phar; запуск: `php composer install`)

### 4. Конфигурация (секреты — локально)
```bash
# из www/includes/
cp config.example.php config.php
```
Затем открыть `www/includes/config.php` и заполнить реальные значения:
- БД: `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` (для локалки обычно root@localhost, пустой пароль);
- amoCRM, reCAPTCHA, ЮKassa — реальные ключи (см. исходную машину/хостинг).

### 5. База данных (из PII-free сида в репозитории)
```bash
# создать БД
mysql -u root -e "CREATE DATABASE IF NOT EXISTS c103264_zippaket_optom_ru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# структура всех таблиц
mysql -u root c103264_zippaket_optom_ru < db/seed/schema.sql
# данные товаров (49 позиций, без персональных данных)
mysql -u root c103264_zippaket_optom_ru < db/seed/products-data.sql
```
> `db/seed/schema.sql` уже включает все изменения миграций (поля упаковки `products`,
> таблицы `orders`/`order_items`). Отдельно `db/migrations/*.sql` применять НЕ нужно —
> они для исходной БД. Сид воспроизводит актуальную схему целиком.

### 6. Проверка
```bash
cd www
php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests
# ожидается: OK (35 tests, ...)  # после Плана 4
```
Запустить сайт: указать `www/` корнем веб-сервера (Apache из Laragon) или
`php -S localhost:8000 -t www` для быстрой проверки страниц.

> На Windows без PHP в PATH используйте полный путь к php.exe
> (напр. `C:\laragon\bin\php\php-8.3.x...\php.exe`).

## Что НЕ в GitHub (и почему)

- `www/includes/config.php`, `www/tg/config.php` — секреты (пароль БД, токены amoCRM,
  ключ reCAPTCHA). Воссоздать из `*.example`/исходной машины.
- `c103264_zippaket_optom_ru.sql` и `.tar.gz` — полный дамп с персональными данными
  лидов. Для разработки НЕ нужен — есть PII-free сид (`db/seed/`). Полные данные брать
  с хостинга при необходимости.
- `www/vendor/`, логи, `www/tg/users/`, `.superpowers/`.

## Удалённое управление сессией (с телефона/другого устройства)

Альтернатива переносу: **Claude Code Remote Control** — на исходном ПК
`claude remote-control --name "Zippaket Optom"`, затем подключиться из приложения
Claude (вкладка Code) по QR. Процесс остаётся на ПК (доступны локальная БД и config).
