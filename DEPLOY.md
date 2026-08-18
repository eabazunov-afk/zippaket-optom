# План деплоя на прод (zippaket-optom.ru)

Чек-лист выката светлой версии (дизайн-система A3 «Бронза») на боевой хостинг.
Пункты, помеченные **[ты]**, требуют доступа к хостингу/аккаунтам — их делаешь ты,
не я.

## 0. Предохранитель: спасти живой прод-код **[ты]** ⚠️ КРИТИЧНО

На проде крутится **отдельная светлая «поиск+витрина» версия, которой НЕТ в git**.
Если выкатить master, не сохранив её, — потеряешь безвозвратно.

```bash
# скачать по FTP/SSH корень сайта (index.php, header/footer, css/, js/, шаблоны)
git checkout -b archive/prod-live-2026-07 master   # или --orphan, если истории нет
# положить скачанные файлы
git add -A && git commit -m "archive: снимок живого прода (светлая поиск+витрина)"
git push -u origin archive/prod-live-2026-07
git checkout feature/light-design-system
```

## 1. Слить стек PR в master

Ветки-фичи стекированы: **#7 (A→master) ← #8 (B→A) ← #9 (C→B) ← #10 (design-system→C)**.
Мержить по порядку, каждую следующую перецелив на master:

1. Смержить **#7** (`feature/vitrina-faza-A` → master).
2. Переключить базу **#8** на master, смержить.
3. Так же **#9**, затем **#10** (`feature/light-design-system`).

> Проверить статус `feature/plan3-tails` (фикс гонки order_number) и `feature/home-*`
> — входят ли в этот релиз или устарели. Сверить с тобой перед мержем.

После мержа: `git checkout master && git pull`.

## 2. Выложить код на хостинг **[ты]**

### 2.1. Пересобрать зависимости без dev-пакетов

Перед заливкой обязательно (иначе на прод уедут PHPUnit и вся его цепочка —
это ~60 МБ лишнего кода с исполняемыми примерами внутри):

```bash
cd www
composer install --no-dev --optimize-autoloader --no-interaction
```

> Composer'а в репозитории нет (`www/composer` — phar, он в `.gitignore`).
> На машине сборки поставить его с https://getcomposer.org/download/.

### 2.2. Залить содержимое `www/` в корень сайта (FTP/SSH/CI)

**НЕ перезаписывать** (боевые данные уже на хостинге):
- `www/includes/config.php` (боевые секреты);
- `www/uploads/`;
- логи (`www/logs/`, `www/includes/*.log`, `www/tg/*.log`).

**НЕ заливать вообще** (dev/сборочные артефакты, на проде не нужны):

| Путь | Почему |
|---|---|
| `www/tests/` | PHPUnit-тесты, боевому коду не нужны |
| `www/composer.json`, `www/composer.lock` | раскрывают версии зависимостей (подбор известных CVE) |
| `www/_dev_router.php` | мёртвый дубль dev-роутера; канон — `router.php` в корне репозитория + `start-dev.ps1`. Кандидат на удаление |
| `www/composer` | phar самого Composer'а (~3 МБ), на проде не нужен; в `.gitignore`, т.е. в свежем клоне его и нет |
| `www/logs/*.log`, `www/includes/*.log` | локальные логи разработки |
| `www/vendor/bin/`, `www/vendor/phpunit/`, `www/vendor/phar-io/`, `www/vendor/sebastian/`, `www/vendor/theseer/`, `www/vendor/myclabs/`, `www/vendor/nikic/`, `www/vendor/doctrine/` | dev-зависимости; исчезают сами после `composer install --no-dev` |
| `.git/`, `db/`, `docs/`, `thoughts/`, `bin/`, `*.sql`, `*.sql.gz`, `*.tar.gz` | лежат вне `www/`, но не должны попасть в корень сайта ни при каком раскладе |

> Если залить всё же проще целиком — страховка уже стоит в `.htaccess`:
> корневой `.htaccess` отдаёт 403 на `/vendor/*` и `/tests/*` (через
> `RedirectMatch`, т.к. `www/vendor/` в `.gitignore` и лежащий там
> `.htaccess` не версионируется) и закрывает `composer.json` /
> `composer.lock` / `_dev_router.php` / `.env*` и все `*.log` / `*.sql` /
> `*.gz` / `*.bak`. Плюс `www/includes/.htaccess` закрывает весь каталог
> `includes/` с единственным исключением — `api.php` (боевой AJAX-эндпоинт).
> Но это именно страховка, а не замена п. 2.1.

### 2.3. Проверить, что защита сработала

После выката открыть в браузере — все должны отдать **403** (или 404):

```
/vendor/autoload.php
/tests/bootstrap.php
/composer.json
/_dev_router.php
/includes/config.php
/includes/init.php
```

А эти — **работать**:

```
/includes/api.php?action=calculate   (боевой AJAX-эндпоинт фронтенда)
/api/cart.php
/api/product.php?id=1&quick=1
```

## 3. Миграции БД на проде **[ты]**

Накатить миграции на боевую БД **по порядку имён файлов**. Все идемпотентны —
можно прогнать весь каталог целиком, уже применённые ничего не сломают
(проверено двойным прогоном на чистой БД 2026-08-18):

```bash
for f in db/migrations/*.sql; do
  echo "applying $f"
  mysql -u USER -p БАЗА < "$f"
done
```

Что именно должно быть на проде:

| Миграция | Без неё ломается |
|---|---|
| `2026-06-18-orders-schema.sql` | заказы (`orders`/`order_items`), поля упаковки товара |
| `2026-06-19-order-access-token.sql` | защита ссылок на заказ/счёт (IDOR) |
| **`2026-07-02-reviews.sql`** | **отзывы: блок на главной, `review_add.php`, `admin/reviews.php`** — обязательна |
| `2026-07-03-settings.sql` | цены материалов калькулятора (`admin/settings.php`) |
| `2026-08-18-admin-roles.sql` | роли `superadmin`/`viewer` в админке |
| `2026-08-18-offer-carts.sql` | сохранение «сборной корзины» расчётов (`includes/api.php`) |

Разбор каждой миграции и откат — в `db/README.md`.

## 4. Конфигурация и боевые доступы **[ты]**

Проверить/заполнить в боевом `www/includes/config.php`:
- **`SELLER_*`** — все заполнены (иначе футер падает Fatal).
- **amoCRM** — токен истёк 31.05.2026, **перевыпустить** (`AMOCRM_ACCESS_TOKEN`).
- **ЮKassa** — `YOOKASSA_SHOP_ID` / `SECRET`, настроить webhook в ЛК на
  `/api/payment_callback.php`.
- **Email** — рабочий MTA на проде (иначе письма не уходят).
- `SUPPORT_PHONE` / `ADMIN_EMAIL` — реальные (проверить, что и в шапке, и в футере).

## 5. Смоук-проверка после выката

- Главная, каталог, карточка товара, корзина, checkout, калькулятор — открываются, светлые, иконки на месте.
- `/sitemap.xml` отдаётся.
- Тестовый заказ → уведомления (Telegram/email/amoCRM) приходят.
- Админка `/admin/` — вход, «Расчёты», «Настройки» (сменить временный пароль!).
- **Консоль браузера пуста от ошибок CSP** (`Refused to load/apply…`) на главной,
  каталоге, карточке товара, checkout и в `/admin/statistics.php` (там Chart.js).
  CSP переписана — проверить, что FontAwesome, Google Fonts, reCAPTCHA,
  Яндекс.Метрика и Chart.js грузятся.
- 301-редиректы ведут на ЧПУ, а не на «сырые» адреса:
  `http://www.zippaket-optom.ru/product/1` → `https://zippaket-optom.ru/product/1`
  (а НЕ на `…/product.php?id=1`); `https://zippaket-optom.ru/index.php` → `/`.

## Открытые решения владельца

- **A4:** категории Stand-Up / вакуумные пакеты — заводить (товары+фото+цены) или убрать из рекламы в футере.
- **Концепция главной** — светлая витрина-поиск (текущая ветка) финальная?
