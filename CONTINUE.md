# Как продолжить работу на другом компьютере

Репозиторий: `https://github.com/eabazunov-afk/zippaket-optom` (приватный).
Весь код, спеки, планы и PII-free сид БД — в GitHub. Локальные секреты и дамп с
персональными данными в репозиторий НЕ попадают (см. раздел «Что не в GitHub»).

## Текущий статус (на 2026-08-19)

Активная ветка — **`fix/audit-2026-08-18`**: аудит проекта завершён, исправления
сделаны четырьмя волнами и закоммичены (12 коммитов поверх
`feature/light-design-system`). Ветка стоит поверх стека
`feature/vitrina-faza-A → B → C → feature/light-design-system`.

⚠️ **В `master` этот стек ещё НЕ слит** (`master` = `04c3624` от 2026-07-01).
Порядок мержа (включая эту ветку последней) расписан в `DEPLOY.md`, п. 1.

Тесты: `OK (174 tests, 716 assertions)` — 2026-08-19, PHPUnit 9.6.34.

### Что исправил аудит

**Оплата и заказы**
- Переход статуса заказа стал атомарным (`UPDATE … WHERE id=? AND status=?`,
  `rowCount()=0` → noop): ретрай webhook ЮKassa больше не шлёт второе уведомление.
- `payment_callback` сверяет `amount.value` с `orders.total` в копейках;
  расхождение → 409 `amount_mismatch`, заказ не переводится в `paid`.
- Оптовые ступени вынесены в чистые функции (`wholesale_tier_for_qty`) и
  применяются в корзине и заказе, а не только на витрине — клиент видел
  «от 300к ×0.82», а счёт получал розничный. Снапшот `order_items` пишет
  применённую цену. В `WHOLESALE_TIERS` добавлен явный `min_qty`.
- Заказ на сумму ≤ 0 не создаётся; товар без цены нельзя положить в корзину.
- Неудача создания платежа больше не показывает «Заказ принят» — экран
  «Оплата не началась» + повтор по ссылке с `access_token`.
- Гонка `order_number`: `INSERT` ретраится со следующим seq при дубликате
  `uq_order_number`.

**Безопасность**
- CSP в `www/.htaccess` собрана по фактическому списку источников (cdnjs, Google
  Fonts, jsdelivr, reCAPTCHA, Метрика) + `base-uri`/`form-action`/
  `frame-ancestors`/`object-src`. Прежняя политика задавала только `script-src`,
  и на проде резала шрифты, иконки, метрику и все инлайн-стили.
- XSS через JSON-LD закрыт: `SEO_JSONLD_FLAGS` во всех генераторах `ld+json`;
  GET-параметры каталога нормализуются к строке (`?search[]=x` роняло страницу).
- Telegram-вебхук стал fail-closed: `bot.php` требует
  `X-Telegram-Bot-Api-Secret-Token` (`hash_equals`), `setwebhook.php` больше не
  доступен анониму, путь файла состояния строится от `__DIR__` (был path traversal
  через `from.id`), TLS-проверка вернулась в отправку.
- Админка: иерархия ролей (`canManageRole`), `checkPageAccess` deny-by-default
  (страница вне матрицы → 403), мутации переведены с GET на POST + CSRF,
  `session_regenerate_id(true)` после логина, счётчик брутфорса по IP отделён
  от блокировки учётки.
- ПДн вычищены из логов (152-ФЗ): удалены `includes/debug.log` (4.7 МБ),
  `php_errors.log`, `bot_debug.log`, `notify_log.txt`, `debug_lead.txt`;
  из `api.php`/`amocrm.php` убраны `print_r`-дампы. Клиенту больше не отдаются
  `getFile()`/`getLine()`/тексты внутренних исключений.
- Закрыты от HTTP `vendor/`, `tests/`, `includes/` (кроме боевого `api.php`),
  `composer.json`/`lock`; удалены `www/api/test.php` и `www/api/index.php`.

**Магазин**
- `saveCalculationToDB`/`saveOfferCartToDB` были заглушками («симуляция
  сохранения», `success:true` без записи) — теперь реальный INSERT в
  `calculations` (цена пересчитывается на сервере) и в `offer_carts`.
- `request_offer` приведён к уровню `save_lead`: reCAPTCHA, `pdn_consent`,
  валидация, экранирование в письме админу.
- CSRF-мета на главной — кнопка «В корзину» перестала молча получать 403.
- Остаток: предупреждение о под-заказе, ограничение qty сверху, снятый с продажи
  товар виден в корзине и блокирует оформление.
- Уведомления ушли с горячего пути (редирект → flush → рассылка), общий бюджет
  15 с; telegram 5 с, amoCRM 7 с с `CONNECTTIMEOUT 3` (было до 4 запросов
  подряд по 30 с без коннект-лимита).
- Каталог при недоступной БД отдаёт пустой список вместо 500.

**Фронтенд**
- `js/script.js` и `js/cart.js` подключаются один раз из `footer.php`: на девяти
  страницах (корзина, оформление, успех, 404 и пять юридических) скриптов не было
  вовсе — гамбургер, мобильное меню и счётчик корзины там не работали.
- Починен невидимый текст (поле телефона белым по белому, `.contacts a:hover`),
  иконки «Почему ZLOCK» переведены с неподключённого Phosphor на FontAwesome,
  `catalog.css` — с холодной палитры на токены «Бронзы».
- Удалено ~1050 строк мёртвого JS; удалён неиспользуемый `css/home-premium.css`.
- Созданы недостающие `images/og-image.jpg` (был 404 в `og:image`) и постер
  фонового видео; добавлены `:focus-visible`, `noindex` на служебных страницах.

**Инфраструктура**
- Индексы каталога (`2026-08-18-indexes.sql`): на таблице в 200 тыс. строк
  EXPLAIN уходит с `ALL`+`filesort` на `ref` без `filesort`, новинки 1.08 с → 0.001 с.
  `WHERE DATE(col)` заменено на полуинтервалы в `statistics.php` и счётчике заказов.
- Миграции 2026-06-18/2026-06-19/2026-07-02 сделаны идемпотентными;
  `db/seed/schema.sql` пересобран — было 12 таблиц вместо 15 (не хватало
  `reviews` и `settings`, установка по документации давала нерабочий сайт).
- `start-dev.ps1`: пути к php/mysqld ищутся по факту (были захардкожены на
  несуществующий каталог), добавлены `-Port`/`-MysqlPort`/`-SkipMysql`,
  файл сохранён с BOM (PowerShell 5.1 падал на кириллице).
- Логи приложения и бота переехали в `LOG_DIR` — каталог `logs/` рядом с `www/`,
  вне веб-корня. Добавлены `FNS_API_KEY`, `YOOKASSA_VAT_CODE` и
  `config_warn_placeholders()` (предупреждение в лог о незаполненных секретах).
- Починен rewrite в `.htaccess`: канонизация HTTPS/www переехала в начало файла
  (раньше на втором проходе редиректила ЧПУ на сырой URL), исправлена битая
  регулярка `^[A-Z]{3,9]`.
- Новые тесты: `OrderCreateTest` (18), `AdminPermissionsTest` (15),
  `TgBotSecurityTest` (11), `OrderPaymentStatusTest`, `OrderAmountTest`,
  `JsonLdEscapingTest`.

⚠️ **Не забыть при выкате** (подробности — `DEPLOY.md`):
перерегистрировать telegram-вебхук (`php www/tg/setwebhook.php` из CLI, п. 4.2),
добавить `TG_WEBHOOK_SECRET` в боевой `www/tg/config.php`, создать каталог
`logs/` с правом записи (п. 4.3), выставить `YOOKASSA_VAT_CODE` (по умолчанию
«без НДС», п. 4.1) и проверить, кого миграция ролей подняла до `superadmin` (п. 3.1).

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
- **Аудит-фиксы** (`fix/audit-2026-08-18`, текущая ветка) — разбор выше,
  в «Что исправил аудит».

> Прежний «премиум-слой главной» `css/home-premium.css` **удалён** (коммит `7974f03`):
> его не подключал ни один шаблон. Главная перекрашена напрямую через токены `--z-*`.

Дальше по дорожной карте:
- Слить стек веток в `master` (`DEPLOY.md`, п. 1, `fix/audit-2026-08-18` — последней)
  и выкатить на прод.
- Боевые доступы: креды ЮKassa, перевыпуск токена amoCRM, MTA для email,
  `TG_WEBHOOK_SECRET` + перерегистрация вебхука бота.
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
- amoCRM, reCAPTCHA, ЮKassa, `FNS_API_KEY` — реальные ключи (см. исходную
  машину/хостинг). Оставленные плейсхолдеры `ВАШ_…` раз в час пишут в лог строку
  `config: не заполнены секреты (…)` — для локалки это нормально;
- `YOOKASSA_VAT_CODE` — ставка НДС в чеке 54-ФЗ, по умолчанию `1` («без НДС»);
- `LOG_DIR` — каталог `logs/` рядом с `www/` (вне веб-корня), туда пишутся
  `app-error.log` и `tg-bot.log`; создаётся кодом сам.

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
> стартовые данные (отзывы, цены материалов калькулятора) и страхуют от того,
> что сид отстал от `db/migrations/`.
> Варианты команд для Windows/PowerShell и разбор каждой миграции — в `db/README.md`
> и `SETUP.md`. Процедура проверена прогоном на чистой БД 2026-08-18.

### 6. Проверка
```bash
cd www
php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests
# ожидается строка OK (...) без F/E.
# последний замер: OK (174 tests, 716 assertions) — 2026-08-19, PHPUnit 9.6.34.
# число растёт с каждой фичей: ориентир — статус OK, а не конкретное число.
```
Запустить сайт — из корня репозитория:
```powershell
.\start-dev.ps1          # http://127.0.0.1:8077/ , админка /admin/
```
Скрипт сам находит PHP и MySQL (Laragon или PATH) и поднимает встроенный
PHP-сервер через `router.php` (эмуляция ЧПУ из `www/.htaccess`).
Без PowerShell — то же вручную: `php -S 127.0.0.1:8077 -t www router.php`.

> На Windows без PHP в PATH используйте полный путь к php.exe
> (напр. `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`).

## Что НЕ в GitHub (и почему)

- `www/includes/config.php`, `www/tg/config.php` — секреты (пароль БД, токены amoCRM,
  ключ reCAPTCHA, `TG_WEBHOOK_SECRET`). Воссоздать из `*.example`/исходной машины.
- `c103264_zippaket_optom_ru.sql` и `.tar.gz` — полный дамп с персональными данными
  лидов. Для разработки НЕ нужен — есть PII-free сид (`db/seed/`). Полные данные брать
  с хостинга при необходимости.
- `www/vendor/` (ставится `composer install`), `www/composer` (phar самого Composer'а —
  ставить с getcomposer.org), логи (`logs/` = `LOG_DIR`, `www/logs/`,
  `www/admin/logs/`), `www/tg/users/` (состояния сессий бота — ПДн), `.superpowers/`.

## Удалённое управление сессией (с телефона/другого устройства)

Альтернатива переносу: **Claude Code Remote Control** — на исходном ПК
`claude remote-control --name "Zippaket Optom"`, затем подключиться из приложения
Claude (вкладка Code) по QR. Процесс остаётся на ПК (доступны локальная БД и config).
