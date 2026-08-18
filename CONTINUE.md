# Как продолжить работу на другом компьютере

Репозиторий: `https://github.com/eabazunov-afk/zippaket-optom` (приватный).
Весь код, спеки, планы и PII-free сид БД — в GitHub. Локальные секреты и дамп с
персональными данными в репозиторий НЕ попадают (см. раздел «Что не в GitHub»).

## Текущий статус (на 2026-08-18)

Активная ветка — **`fix/audit-2026-08-18`** (исправления по итогам аудита:
безопасность, CSP, роли админки, telegram-вебхук, сохранение заявок, миграции БД).
Она стоит поверх стека `feature/vitrina-faza-A → B → C → feature/light-design-system`.

⚠️ **В `master` этот стек ещё НЕ слит** (`master` = `04c3624` от 2026-07-01).
Порядок мержа расписан в `DEPLOY.md`, п. 1.

### Сделано и уже в `master`
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

- **План 5 — Уведомления**: при оформлении и оплате заказа — Telegram (админу),
  Email (админу/клиенту), лид в amoCRM. Чистые форматтеры + best-effort каналы
  (`includes/notify/`), wiring в checkout и webhook. Код готов; ⚠️ для amoCRM нужен
  перевыпуск токена (истёк 31.05.2026), для email — рабочий MTA на проде.
  См. `docs/superpowers/plans/2026-06-18-уведомления.md`.
- **План 6 — Premium-редизайн (путь покупки) + SEO**: тема `css/premium.css` по флагу
  `body.premium` на каталоге/товаре/корзине/checkout/успехе; динамический `sitemap.xml`,
  BreadcrumbList, canonical, фикс бага `SITE_URL`. Главная — отдельной итерацией.
  См. `docs/superpowers/plans/2026-06-18-редизайн-и-seo.md`.

### Сделано после этого (в ветках, в `master` ещё не слито)

- **Витрина, фаза A** (`feature/vitrina-faza-A`): главная пересобрана в B2B-витрину —
  hero с поиском-подбором, секции «Хиты продаж» и «Новинки» из БД, опт-цены
  (`WHOLESALE_TIERS` в config), лид-механики: «Запросить КП» (RFQ), быстрый заказ
  в 1 клик, лид-магнит «Скачать прайс» (XLS из БД, `www/price.php`).
- **Витрина, фаза B** (`feature/vitrina-faza-B`): отзывы в БД (`reviews`) с публичной
  формой и модерацией в админке, блок кейсов, FAQ по опту (+FAQPage schema),
  расширенные гарантии/документы, плавающий виджет мессенджеров.
- **Витрина, фаза C** (`feature/vitrina-faza-C`): SEO/перф — Product JSON-LD
  с aggregateRating, ItemList для каталога, canonical/OG на статике, LCP/CLS hero.
- **Светлая дизайн-система A3 «Бронза»** (`feature/light-design-system`): токены
  `--z-*` перекрашены из тёмных в светлые, типографика Fraunces, консолидация
  `--pm-*`, тёплая серая шкала, единый набор иконок FontAwesome (Phosphor отменён,
  коммит `e9a2d48`). Редизайн админки, страница «Расчёты», цены материалов
  калькулятора редактируются в админке (таблица `settings`).
- **Аудит-фиксы** (`fix/audit-2026-08-18`, текущая ветка): CSP, XSS в JSON-LD и
  параметрах каталога, атомарный переход статуса заказа, реальное сохранение
  заявок, секрет на telegram-вебхуке, роли админки, миграции `2026-08-18-*`.

> Прежний «премиум-слой главной» `css/home-premium.css` **больше не используется**:
> файл существует, но ни один шаблон его не подключает (`grep -rn home-premium www/`
> даёт только комментарий внутри самого файла). Главная перекрашена напрямую через
> токены `--z-*`. Кандидат на удаление.

Дальше по дорожной карте:
- Слить стек веток в `master` (`DEPLOY.md`, п. 1) и выкатить на прод.
- Боевые доступы: креды ЮKassa, перевыпуск токена amoCRM, MTA для email.
- Решения владельца: категории Stand-Up/вакуумные (A4), финальность концепции главной.

Артефакты: спеки — `docs/superpowers/specs/`, планы — `docs/superpowers/plans/`
(у каждого плана в шапке блок «Статус»), сверка с продом —
`docs/расхождения-прод-vs-master.md`, research — `thoughts/research/`.

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
Composer'а в свежем клоне **нет**: `www/composer` (phar) лежит в `.gitignore`,
файла `composer.phar` в репозитории тоже нет. Поставь Composer сам —
https://getcomposer.org/download/ (или `winget install Composer`), затем:

```bash
cd www
composer install
```

### 4. Конфигурация (секреты — локально)
```bash
# из www/includes/
cp config.example.php config.php
```
Затем открыть `www/includes/config.php` и заполнить реальные значения:
- БД: `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` (для локалки обычно root@localhost, пустой пароль);
- **все `SELLER_*`** — обязательны, иначе футер падает с Fatal (список — в `SETUP.md`, п. 2);
- amoCRM, reCAPTCHA, ЮKassa — реальные ключи (см. исходную машину/хостинг).

### 5. База данных (из PII-free сида в репозитории)
```bash
# создать БД
mysql -u root -e "CREATE DATABASE IF NOT EXISTS c103264_zippaket_optom_ru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# структура всех таблиц
mysql -u root c103264_zippaket_optom_ru < db/seed/schema.sql
# данные товаров (49 позиций, без персональных данных)
mysql -u root c103264_zippaket_optom_ru < db/seed/products-data.sql
# миграции — по порядку имён, все идемпотентны
for f in db/migrations/*.sql; do mysql -u root c103264_zippaket_optom_ru < "$f"; done
```
> Миграции применять **обязательно**: сид — снимок схемы, а миграции ещё и досыпают
> стартовые данные (отзывы, цены материалов калькулятора, роль `superadmin`).
> Варианты команд для Windows/PowerShell и разбор каждой миграции — в `db/README.md`
> и `SETUP.md`. Процедура проверена прогоном на чистой БД 2026-08-18.

### 6. Проверка
```bash
cd www
php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests
# ожидается строка OK (...) без F/E.
# последний замер: OK (124 tests, 450 assertions) — 2026-08-18, PHPUnit 9.6.34.
# число растёт с каждой фичей: ориентир — статус OK, а не конкретное число.
```
Запустить сайт — из корня репозитория:
```powershell
.\start-dev.ps1          # http://127.0.0.1:8000/ , админка /admin/
```
Скрипт сам находит PHP и MySQL (Laragon или PATH) и поднимает встроенный
PHP-сервер через `router.php` (эмуляция ЧПУ из `www/.htaccess`).
Без PowerShell — то же вручную: `php -S 127.0.0.1:8000 -t www router.php`.

> На Windows без PHP в PATH используйте полный путь к php.exe
> (напр. `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`).

## Что НЕ в GitHub (и почему)

- `www/includes/config.php`, `www/tg/config.php` — секреты (пароль БД, токены amoCRM,
  ключ reCAPTCHA). Воссоздать из `*.example`/исходной машины.
- `c103264_zippaket_optom_ru.sql` и `.tar.gz` — полный дамп с персональными данными
  лидов. Для разработки НЕ нужен — есть PII-free сид (`db/seed/`). Полные данные брать
  с хостинга при необходимости.
- `www/vendor/` (ставится `composer install`), `www/composer` (phar самого Composer'а —
  ставить с getcomposer.org), логи, `www/tg/users/`, `.superpowers/`.

## Удалённое управление сессией (с телефона/другого устройства)

Альтернатива переносу: **Claude Code Remote Control** — на исходном ПК
`claude remote-control --name "Zippaket Optom"`, затем подключиться из приложения
Claude (вкладка Code) по QR. Процесс остаётся на ПК (доступны локальная БД и config).
