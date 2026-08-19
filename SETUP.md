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
git checkout fix/audit-2026-08-18   # актуальная ветка работы (поверх стека витрины и A3)
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
оставить заглушками — оплата/CRM просто не будут работать. Config это заметит:
`config_warn_placeholders()` раз в час пишет в лог строку
`config: не заполнены секреты (…)` — на локалке это норма, на проде — повод
дозаполнить (`DEPLOY.md`, п. 4.1).

Отдельно про `LOG_DIR`: он указывает на каталог `logs/` **рядом с `www/`**
(вне веб-корня) — туда пишутся `app-error.log` (из `includes/api.php`) и
`tg-bot.log` (из `tg/bot_lib.php`). Каталог создаётся кодом сам; если прав нет,
логи молча уходят в системный `error_log` PHP. Каталог в `.gitignore`.

Telegram-бот локально не нужен. Если всё же поднимаешь — `www/tg/config.php`
из `www/tg/config.example.php`: кроме `BOT_TOKEN`/`ADMIN_CHAT_ID` там обязателен
`TG_WEBHOOK_SECRET`, без него `tg/bot.php` отвечает 403 на всё (fail-closed).

## 3. База данных

Имя базы по умолчанию — **`c103264_zippaket_optom_ru`** (оно же в `config.example.php`
и на хостинге). Если возьмёшь другое — не забудь `DB_NAME` в `config.php`.

**Вариант А (штатный) — сид из репозитория.** Схема + 49 товаров, без персональных
данных. Работает в свежем клоне, ничего скачивать не надо.

PowerShell (Windows, MySQL из Laragon не в PATH):

```powershell
$mysql = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
$db    = "c103264_zippaket_optom_ru"
& $mysql -u root -e "CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "`"$mysql`" -u root $db < db\seed\schema.sql"
cmd /c "`"$mysql`" -u root $db < db\seed\products-data.sql"
```

Bash (Linux/macOS, mysql в PATH):

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS c103264_zippaket_optom_ru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p c103264_zippaket_optom_ru < db/seed/schema.sql
mysql -u root -p c103264_zippaket_optom_ru < db/seed/products-data.sql
```

**Вариант Б — полный дамп с боевыми данными.** Файла `c103264_zippaket_optom_ru.sql`
**в репозитории нет** (`.gitignore` режет `*.sql` — там персональные данные лидов).
Этот вариант доступен, только если ты сам скачал дамп с хостинга или перенёс
с исходной машины:

```bash
mysql -u root -p c103264_zippaket_optom_ru < c103264_zippaket_optom_ru.sql
```

### Миграции

После заливки базы накати миграции по порядку имён. Все они идемпотентны —
повторный запуск не падает и не плодит дубликаты; на свежем сиде они лишь
досыпают стартовые строки (отзывы, цены калькулятора) — `schema.sql` уже содержит
мигрированную схему, так что `ALTER`-части проходят вхолостую. Прогонять их всё
равно надо: сид может отстать от `db/migrations/`.

PowerShell:

```powershell
Get-ChildItem db\migrations\*.sql | Sort-Object Name | ForEach-Object {
    Write-Host "applying $($_.Name)"
    cmd /c "`"$mysql`" -u root $db < `"$($_.FullName)`""
}
```

> `cmd /c`, а не `mysql -e "source ..."`: в пути к репозиторию кириллица
> (`…\Documents\Сайт\…`), и `source` такой файл не открывает.

Bash:

```bash
for f in db/migrations/*.sql; do
  echo "applying $f"
  mysql -u root -p c103264_zippaket_optom_ru < "$f"
done
```

Список на текущий момент:
- `2026-06-18-orders-schema.sql` — поля упаковки у товаров + таблицы заказов
- `2026-06-19-order-access-token.sql` — токен доступа к заказу (IDOR-фикс)
- `2026-07-02-reviews.sql` — отзывы
- `2026-07-03-settings.sql` — настройки калькулятора (цены материалов)
- `2026-08-18-admin-roles.sql` — роли админов (`superadmin`/`viewer`) + подъём
  старейшего активного админа до `superadmin`, если суперадмина ещё нет
- `2026-08-18-indexes.sql` — составные индексы каталога (`products`) + `ANALYZE TABLE`
- `2026-08-18-offer-carts.sql` — «сборная корзина» расчётов

Подробности (что делает каждая, откат, как пересобрать сид) — `db/README.md`.

### Админ-пользователь

Аккаунты админки лежат в таблице `admins` и **не** передаются через git.
Если базу залил из дампа — там уже есть `admin` (пароль знаешь ты). Если нет —
заведи запись вручную (`password_hash` через
`password_hash('пароль', PASSWORD_DEFAULT)`).

Роли после `2026-08-18-admin-roles.sql`: `superadmin` > `admin` > `manager` >
`viewer`. Заводи себе **`superadmin`** — управление учётками (`edit_users`,
`delete_users`) есть только у него. Если завёл `admin` и он единственный активный,
миграция сама поднимет его до `superadmin` при следующем прогоне (она
идемпотентна и повышает только когда суперадмина нет вовсе).

## 4. Запуск дев-сервера

**Канонический способ — `start-dev.ps1`** (он же поднимет MySQL, если тот не запущен):

```powershell
.\start-dev.ps1              # http://127.0.0.1:8077/
.\start-dev.ps1 -Port 8123   # другой порт
.\start-dev.ps1 -SkipMysql   # если MySQL уже поднят Laragon'ом
```

PHP и MySQL скрипт ищет сам в `C:\laragon`, `%USERPROFILE%\laragon` и в PATH —
версии в путях не захардкожены.

Если PowerShell недоступен — то же самое руками (роутер `router.php` эмулирует
ЧПУ-правила `www/.htaccess`):

```bash
php -S 127.0.0.1:8077 -t www router.php
```

Открыть: http://127.0.0.1:8077/ · админка: http://127.0.0.1:8077/admin/

> `127.0.0.1`, а не `localhost`: на Windows `localhost` резолвится в `::1`,
> и `php -S` садится только на IPv6.
>
> Роутер в проекте один — `router.php` в корне репозитория. Более ранний дубль
> `www/_dev_router.php` удалён; правило, закрывающее его в `www/.htaccess`,
> оставлено на случай старых копий на проде.

## 5. Тесты

Composer в репозитории **нет** (`www/composer` — phar, он в `.gitignore`).
В свежем клоне поставь его: https://getcomposer.org/download/ (либо
`winget install Composer` / пакетный менеджер системы).

```bash
cd www
composer install          # один раз, ставит PHPUnit
php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests
```

Ожидаемо — строка `OK (...)` без единого F/E.
Последний замер: `OK (174 tests, 716 assertions)` — 2026-08-19, PHPUnit 9.6.34.
Число тестов растёт с каждой фичей; ориентир — статус `OK`, а не конкретное число.

---

## Что синхронизируется, а что нет

| Через git (есть на GitHub) | Только локально (настроить заново) |
|---|---|
| Весь код, шаблоны, CSS/JS | `www/includes/config.php` (пароли/ключи) |
| Миграции и сиды БД (`db/`) | `www/tg/config.php` (токен бота, `TG_WEBHOOK_SECRET`) |
| Тесты, документация | Сама база данных (данные) |
| `router.php`, `start-dev.ps1` | Загруженные файлы в `www/uploads/` |
| `*.example.php` (шаблоны конфигов) | Аккаунты админки |
| | Логи (`logs/`, `www/logs/`, `www/admin/logs/`) |
| | `www/vendor/` (ставится `composer install`) |
| | Composer (`www/composer` — phar, в `.gitignore`) |
| | Полный дамп БД (`*.sql`, `*.tar.gz`) |
