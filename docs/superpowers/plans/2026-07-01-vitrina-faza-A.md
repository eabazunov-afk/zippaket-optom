# Витрина — Фаза A (P0: продающая главная + лид-механики) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Пересобрать главную из имиджевого лендинга в продающую B2B-витрину: hero-оффер под опт с поиском-подбором и двумя CTA, секции «Хиты» и «Новинки» из БД, открытые опт-цены, единые контакты, и три лид-механики (запросить КП, быстрый заказ в 1 клик, скачать прайс XLS).

**Architecture:** Чистая логика отбора/форматирования выносится в `includes/home_view.php` и покрывается PHPUnit без БД (паттерн проекта). Данные — только из БД через класс `Catalog`. Лид-механики переиспользуют существующий экшен-роутер `includes/api.php` (`request_offer`, `save_lead`) и `saveLeadToDB()`. Прайс-XLS строится из БД через уже установленный `phpoffice/phpspreadsheet`. Опт-множители скидок выносятся из хардкода в `config.php`.

**Tech Stack:** PHP 8.3, MySQL 8.x (PDO), PHPUnit 9.6, PhpSpreadsheet (в `vendor/`), ванильный JS. Тёмная тема — `css/site-premium.css` + `css/home.css`.

## Global Constraints

- PHP путь (не в PATH): `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`.
- Запуск тестов из `www/`: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → ожидается `OK`.
- Тесты — на чистых функциях; БД в тестах НЕ трогаем (массивы в памяти).
- Данные товаров — только из БД (`products`, `is_active=1`, `price_rub>0`). Никаких захардкоженных массивов товаров.
- Контакты (телефон, email) — только из констант `config.php`. Плейсхолдеров (`8 (800) 123-45-67`, `info@…`) в разметке быть не должно.
- Опт-градация: розница / опт −8% / опт −18% (текущие множители `1.0 / 0.92 / 0.82`) — вынести в константу, не хардкодить в разметке.
- Тексты — русский, деловой тон опта. Тёмная тема — базовая.
- Поля товара из БД: `id, category, full_name, short_name, width, height, thickness, color, features, unit, min_order_qty, qty_step, quantity_sold, stock_quantity, price_rub, image_url, created_at`.
- Лид пишется через `saveLeadToDB(array $data)` (`includes/api.php:510`); поля: `name, phone, email, type, parameters(JSON), comment, source`. Экшены API: `request_offer`, `save_lead` (роутер `includes/api.php:64`).
- Антипаттерны запрещены: фейковые таймеры, накрученные «осталось N шт», агрессивные поп-апы, скрытые цены.

---

## Task 1: View-хелперы главной + отбор хитов/новинок

Вынести `z_price`/`z_size` из `index.php` в тестируемый модуль и добавить чистую логику отбора хитов/новинок и опт-градации.

**Files:**
- Create: `www/includes/home_view.php`
- Test: `www/tests/HomeViewTest.php`
- Modify: `www/index.php` (заменить локальные `z_price`/`z_size` на `require_once`)

**Interfaces:**
- Produces:
  - `home_price(float $base, float $mult): string` — `"3 849,40"`.
  - `home_size(?int $w, ?int $h): string` — `"25 × 30 см"` из мм; `""` если размеров нет.
  - `home_pick_new(array $products, int $limit = 4): array` — по `created_at` убыв.
  - `home_pick_hits(array $products, int $limit = 8): array` — по `quantity_sold` убыв., затем `stock_quantity` убыв.
  - `home_tiers(): array` — массив опт-уровней из `WHOLESALE_TIERS` (см. Task 3) или дефолт.

- [ ] **Step 1: Написать падающий тест**

```php
<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/home_view.php';

class HomeViewTest extends TestCase
{
    public function testPriceFormatsRubWithThousandsAndComma(): void
    {
        $this->assertSame('3 849,40', home_price(3849.40, 1.0));
        $this->assertSame('3 541,45', home_price(3849.40, 0.92)); // опт −8%
    }

    public function testSizeConvertsMmToCm(): void
    {
        $this->assertSame('25 × 30 см', home_size(250, 300));
        $this->assertSame('', home_size(null, 300));
    }

    public function testPickNewSortsByCreatedAtDesc(): void
    {
        $p = [
            ['id' => 1, 'created_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'created_at' => '2026-06-01 00:00:00'],
            ['id' => 3, 'created_at' => '2026-03-01 00:00:00'],
        ];
        $this->assertSame([2, 3], array_column(home_pick_new($p, 2), 'id'));
    }

    public function testPickHitsSortsBySoldThenStock(): void
    {
        $p = [
            ['id' => 1, 'quantity_sold' => 10, 'stock_quantity' => 500],
            ['id' => 2, 'quantity_sold' => 50, 'stock_quantity' => 10],
            ['id' => 3, 'quantity_sold' => 50, 'stock_quantity' => 900],
        ];
        $this->assertSame([3, 2, 1], array_column(home_pick_hits($p, 3), 'id'));
    }
}
```

- [ ] **Step 2: Запустить тест — убедиться, что падает**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/HomeViewTest.php`
Expected: FAIL — `Failed opening required '.../includes/home_view.php'`.

- [ ] **Step 3: Реализовать `includes/home_view.php`**

```php
<?php
/**
 * Чистые view-хелперы главной. Без БД и без вывода — только форматирование
 * и отбор, чтобы покрыть тестами (tests/HomeViewTest.php).
 */

/** Цена уровня из базовой цены и множителя: "3 849,40". */
function home_price(float $base, float $mult): string {
    return number_format($base * $mult, 2, ',', ' ');
}

/** Размер из мм в "25 × 30 см". Пустая строка, если размеров нет. */
function home_size(?int $w, ?int $h): string {
    $fmt = function ($mm) {
        return rtrim(rtrim(number_format($mm / 10, 1, '.', ''), '0'), '.');
    };
    if (!$w || !$h) return '';
    return $fmt($w) . ' × ' . $fmt($h) . ' см';
}

/** Новинки: по created_at убыванию, первые $limit. Не мутирует вход. */
function home_pick_new(array $products, int $limit = 4): array {
    usort($products, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($products, 0, $limit);
}

/** Хиты: по quantity_sold убыв., при равенстве — по stock_quantity убыв. */
function home_pick_hits(array $products, int $limit = 8): array {
    usort($products, function ($a, $b) {
        $s = (int)($b['quantity_sold'] ?? 0) <=> (int)($a['quantity_sold'] ?? 0);
        return $s !== 0 ? $s : ((int)($b['stock_quantity'] ?? 0) <=> (int)($a['stock_quantity'] ?? 0));
    });
    return array_slice($products, 0, $limit);
}

/** Опт-уровни: из WHOLESALE_TIERS (config) или дефолт. */
function home_tiers(): array {
    if (defined('WHOLESALE_TIERS') && is_array(WHOLESALE_TIERS)) {
        return WHOLESALE_TIERS;
    }
    return [
        ['label' => 'Опт от 300к', 'mult' => 0.82, 'class' => 'p-main'],
        ['label' => 'Опт от 20к',  'mult' => 0.92, 'class' => 'p-sec'],
        ['label' => 'Розница от 3к','mult' => 1.0,  'class' => 'p-sec'],
    ];
}
```

- [ ] **Step 4: Запустить тест — убедиться, что проходит**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/HomeViewTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Подключить хелперы в `index.php`, убрать дубли**

В `www/index.php` после `require_once` конфига добавить `require_once __DIR__ . '/includes/home_view.php';`.
Удалить локальные определения `z_price()` (`index.php:48`) и `z_size()`. В `z_card()` заменить `z_price(` → `home_price(` и `z_size(` → `home_size(`.

- [ ] **Step 6: Прогнать весь набор — регрессий нет**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests`
Expected: `OK` (60 прежних + 4 новых = 64 tests).

- [ ] **Step 7: Commit**

```bash
git add www/includes/home_view.php www/tests/HomeViewTest.php www/index.php
git commit -m "refactor(home): view-хелперы в home_view.php + тесты отбора хитов/новинок"
```

---

## Task 2: `Catalog::getNewProducts()` — отбор новинок

Хиты уже есть (`getPopularProducts()`); для «Новинок» нужен отбор по свежести.

**Files:**
- Modify: `www/includes/catalog_functions.php` (метод в класс `Catalog`, рядом с `getPopularProducts`)

**Interfaces:**
- Produces: `Catalog::getNewProducts(int $limit = 4): array` — активные товары с ценой, `created_at DESC`.

- [ ] **Step 1: Реализовать метод**

```php
    /** Новинки — самые свежие активные товары с ценой. */
    public function getNewProducts($limit = 4) {
        try {
            $sql = "SELECT * FROM products
                    WHERE is_active = 1 AND price_rub > 0
                    ORDER BY created_at DESC, id DESC
                    LIMIT " . (int)$limit;
            return $this->db->query($sql)->fetchAll();
        } catch (PDOException $e) {
            error_log("Ошибка получения новинок: " . $e->getMessage());
            return [];
        }
    }
```

- [ ] **Step 2: Проверить рендер через встроенный сервер**

Поднять сервер:
```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -S 127.0.0.1:8077 -t www router.php
```
В отдельной вкладке:
```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -r "chdir('www'); require 'includes/catalog_functions.php'; $c=new Catalog(); var_dump(count($c->getNewProducts(4)));"
```
Expected: `int(4)` (или меньше, если товаров мало), без ошибок PDO.

- [ ] **Step 3: Commit**

```bash
git add www/includes/catalog_functions.php
git commit -m "feat(catalog): getNewProducts() — отбор новинок по created_at"
```

---

## Task 3: Опт-множители в config (параметризация скидок)

Убрать хардкод `0.82 / 0.92 / 1.0` из разметки — вынести в одну константу, читаемую `home_tiers()`.

**Files:**
- Modify: `www/includes/config.php` (добавить `WHOLESALE_TIERS`)
- Modify: `www/includes/config.example.php` (та же константа-пример)
- Test: `www/tests/HomeViewTest.php` (добавить тест `home_tiers` с определённой константой)

**Interfaces:**
- Consumes: `WHOLESALE_TIERS` из config. Produces: (уже) `home_tiers()`.

- [ ] **Step 1: Добавить тест на чтение константы**

Добавить в `HomeViewTest`:
```php
    public function testTiersFallbackShape(): void
    {
        $tiers = home_tiers();
        $this->assertNotEmpty($tiers);
        $this->assertArrayHasKey('mult', $tiers[0]);
        $this->assertArrayHasKey('label', $tiers[0]);
        $this->assertArrayHasKey('class', $tiers[0]);
    }
```

- [ ] **Step 2: Запустить — убедиться, что проходит (дефолт-ветка)**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/HomeViewTest.php`
Expected: PASS (5 tests) — тест валиден и на дефолте `home_tiers()`.

- [ ] **Step 3: Добавить константу в `config.php`**

В `www/includes/config.php` (рядом с прочими `define`):
```php
// Оптовая градация цен: множитель к базовой price_rub. Порядок = порядок вывода.
define('WHOLESALE_TIERS', [
    ['label' => 'Опт от 300к', 'mult' => 0.82, 'class' => 'p-main'],
    ['label' => 'Опт от 20к',  'mult' => 0.92, 'class' => 'p-sec'],
    ['label' => 'Розница от 3к','mult' => 1.0,  'class' => 'p-sec'],
]);
```
То же добавить в `www/includes/config.example.php`.

- [ ] **Step 4: Переключить `z_card()` в `index.php` на `home_tiers()`**

В `www/index.php` в `z_card()` заменить три захардкоженные строки цен (`index.php:92-94`) циклом:
```php
<?php foreach (home_tiers() as $t): ?>
    <div class="row"><span><?= htmlspecialchars($t['label']) ?></span><span class="<?= htmlspecialchars($t['class']) ?>"><?= home_price($base, $t['mult']) ?> ₽/шт</span></div>
<?php endforeach; ?>
```

- [ ] **Step 5: Рендер-проверка карточки**

Поднять сервер (Task 2 Step 2), открыть `http://127.0.0.1:8077/`. Ожидается: в карточках 3 строки цен (Опт 300к / Опт 20к / Розница), значения совпадают с прежними. Grep, что хардкода множителей не осталось:
Run: `rg -n "0\.82|0\.92" www/index.php` → Expected: пусто.

- [ ] **Step 6: Прогнать весь набор**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests`
Expected: `OK` (65 tests).

- [ ] **Step 7: Commit**

```bash
git add www/includes/config.php www/includes/config.example.php www/index.php www/tests/HomeViewTest.php
git commit -m "refactor(home): опт-множители в WHOLESALE_TIERS (config), без хардкода в разметке"
```

---

## Task 4: Hero-оффер под опт + поиск-подбор

Заменить имиджевый hero деловым: оффер, 3–4 маркера доверия, два CTA (`Скачать прайс` / `Запросить КП`), строка поиска-подбора (GET в каталог).

**Files:**
- Modify: `www/index.php` (hero-секция + получение толщин)
- Modify: `www/css/home.css` (стили `.z-hero-search`, `.z-hero-trust`)

**Interfaces:**
- Consumes: `Catalog::getThicknesses(): int[]` (существует).

- [ ] **Step 1: В `index.php` получить толщины для селекта**

После инициализации каталога добавить:
```php
require_once __DIR__ . '/includes/catalog_functions.php';
$zCatalog = $zCatalog ?? new Catalog();
$zThicknesses = $zCatalog->getThicknesses();
```

- [ ] **Step 2: Заменить содержимое hero деловым блоком**

В hero-контейнере (`index.php:175-206`) подзаголовок/CTA заменить на:
```php
<p class="z-hero-sub">ZIP-пакеты оптом от производителя со склада в РФ. Минимальная партия, наличие, отгрузка от 1 дня, работа с НДС.</p>
<div class="z-hero-trust">
    <span>✔ Мин. партия от <?= (int)($zHits[0]['min_order_qty'] ?? 1000) ?> шт</span>
    <span>✔ В наличии на складе</span>
    <span>✔ Документы, НДС</span>
    <span>✔ Отгрузка от 1 дня</span>
</div>
<form class="z-hero-search" action="/katalog_zip_paketov/" method="get" role="search">
    <input type="text" name="search" class="z-hs-input" placeholder="Размер (25x30), «zip-lock» или артикул" aria-label="Поиск пакетов">
    <select name="type" class="z-hs-select" aria-label="Тип замка">
        <option value="">Любой тип</option>
        <option value="slider">Слайдер</option>
        <option value="ziplock">ZIP-LOCK (гриппер)</option>
    </select>
    <select name="thickness" class="z-hs-select" aria-label="Толщина">
        <option value="">Толщина</option>
        <?php foreach ($zThicknesses as $t): ?>
            <option value="<?= (int)$t ?>"><?= (int)$t ?> мкм</option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="z-btn z-btn-accent z-hs-submit">Найти</button>
</form>
<div class="z-hero-cta">
    <a href="/price.php" class="z-btn z-btn-gold">Скачать прайс</a>
    <a href="#leadForm" class="z-btn z-btn-glass js-rfq" data-rfq="hero">Запросить КП</a>
</div>
```
(`$zHits` появится в Task 5; до него `min_order_qty` возьмётся из дефолта `1000`.)

- [ ] **Step 3: Стили hero** — в `www/css/home.css` добавить `.z-hero-search` (flex-строка на десктопе, колонка на мобайле; поля с тёмным фоном `var(--z-surface-2)`, золотая кнопка `.z-btn-accent`), `.z-hero-trust` (флекс-строка маркеров), `.z-hero-cta` (кнопки в ряд). Использовать существующие токены `--z-*`.

- [ ] **Step 4: Проверка — поиск ведёт в каталог с фильтром**

Поднять сервер, открыть `http://127.0.0.1:8077/`, ввести `25x30`, выбрать «Слайдер», нажать «Найти». Ожидается переход на `/katalog_zip_paketov/?search=25x30&type=slider&thickness=` и отфильтрованная выдача. Grep hero:
Run: `rg -n "z-hero-search|Запросить КП|Скачать прайс" www/index.php` → Expected: совпадения есть.

- [ ] **Step 5: Commit**

```bash
git add www/index.php www/css/home.css
git commit -m "feat(home): деловой hero — оффер, маркеры доверия, поиск-подбор, 2 CTA"
```

---

## Task 5: Секция «Хиты продаж» из БД

Витрина ходовых карточек с ценой, опт-градацией и остатком — сразу под hero.

**Files:**
- Modify: `www/index.php` (данные + секция после hero)

**Interfaces:**
- Consumes: `Catalog::getPopularProducts(int): array`, `home_pick_hits()`, `z_card()`.

- [ ] **Step 1: Данные** — в начале `index.php` (до вывода hero, т.к. hero использует `$zHits[0]`):

```php
$zHits = home_pick_hits($zCatalog->getPopularProducts(12), 8);
```

- [ ] **Step 2: Разметка секции** (сразу после hero)

```php
<?php if ($zHits): ?>
<section class="z-sec z-hits" id="hits" data-reveal>
    <div class="z-wrap">
        <div class="z-sec-head z-center">
            <h2>Хиты продаж</h2>
            <p class="z-sec-sub">Самые ходовые размеры — в наличии, с оптовыми ценами</p>
        </div>
        <div class="z-prod-grid">
            <?php foreach ($zHits as $r): ?>
                <?= z_card($r, mb_stripos((string)$r['category'], 'слайдер') !== false, $zGripperMax ?: 1) ?>
            <?php endforeach; ?>
        </div>
        <div class="z-center"><a href="/katalog_zip_paketov/" class="z-btn z-btn-ghost">Весь каталог →</a></div>
    </div>
</section>
<?php endif; ?>
```

- [ ] **Step 3: Проверка** — открыть главную, снять скриншот (`zshots\shoot.py`, если есть). Секция «Хиты продаж» видна, до 8 карточек с ценами (3 опт-уровня) и остатком, кнопка ведёт в каталог. Grep:
Run: `rg -n "z-hits|Хиты продаж" www/index.php` → Expected: совпадения есть.

- [ ] **Step 4: Commit**

```bash
git add www/index.php
git commit -m "feat(home): секция «Хиты продаж» — витрина из БД с ценами и остатком"
```

---

## Task 6: Секция «Новинки» из БД

**Files:**
- Modify: `www/index.php` (данные + секция после «Хитов»)

**Interfaces:**
- Consumes: `Catalog::getNewProducts(int): array`, `home_pick_new()`, `z_card()`.

- [ ] **Step 1: Данные** — рядом с `$zHits`:

```php
$zNew = home_pick_new($zCatalog->getNewProducts(8), 4);
```

- [ ] **Step 2: Разметка** (после секции «Хиты»)

```php
<?php if ($zNew): ?>
<section class="z-sec z-new" id="new" data-reveal>
    <div class="z-wrap">
        <div class="z-sec-head z-center"><h2>Новинки</h2></div>
        <div class="z-prod-grid">
            <?php foreach ($zNew as $r): ?>
                <?= z_card($r, mb_stripos((string)$r['category'], 'слайдер') !== false, $zGripperMax ?: 1) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
```

- [ ] **Step 3: Проверка** — скриншот: секция «Новинки» с ≤4 карточками. Пустой блок не рендерится, если новинок нет.

- [ ] **Step 4: Commit**

```bash
git add www/index.php
git commit -m "feat(home): секция «Новинки» (created_at DESC)"
```

---

## Task 7: Единые контакты из config + полоса опт-условий

Убрать плейсхолдер `SUPPORT_PHONE` и любые захардкоженные контакты; добавить явную полосу опт-условий.

**Files:**
- Modify: `www/includes/config.php` (реальный `SUPPORT_PHONE`)
- Modify: `www/footer.php`, `www/header.php` (контакты из констант)
- Modify: `www/index.php` (полоса `.z-opt-terms` под hero/хитами)

**Interfaces:**
- Consumes: `SUPPORT_PHONE`, `ADMIN_EMAIL` из config.

- [ ] **Step 1: Проставить реальный телефон в config**

В `www/includes/config.php:38` заменить плейсхолдер `'8 (800) 123-45-67'` на реальный номер компании (подтверждён владельцем): `define('SUPPORT_PHONE', '+7 (920) 346-50-67');`. Ссылка `tel:` соберётся как `+79203465067`.

- [ ] **Step 2: Контакты в шапке/футере — только из констант**

В `www/footer.php` и `www/header.php` найти любой захардкоженный телефон/email (`rg -n "8 \(800\)|@zip|info@|ZTR37" www/header.php www/footer.php`) и заменить на:
```php
<a href="tel:<?= preg_replace('/[^0-9+]/', '', SUPPORT_PHONE) ?>"><?= htmlspecialchars(SUPPORT_PHONE) ?></a>
<a href="mailto:<?= htmlspecialchars(ADMIN_EMAIL) ?>"><?= htmlspecialchars(ADMIN_EMAIL) ?></a>
```

- [ ] **Step 3: Полоса опт-условий** — в `index.php` под hero (или под «Хитами»):

```php
<div class="z-opt-terms" data-reveal>
    <span>✔ Мин. заказ от <?= (int)($zHits[0]['min_order_qty'] ?? 1000) ?> шт</span>
    <span>✔ Скидки от объёма: −8% от 20 000 · −18% от 300 000</span>
    <span>✔ Доставка по РФ · документы, НДС</span>
    <span>✔ Отгрузка от 1 дня</span>
</div>
```

- [ ] **Step 4: Проверка — плейсхолдеров нет**

Run: `rg -n "8 \(800\) 123-45-67|info@zip" www/` → Expected: пусто.
Открыть главную/футер: телефон и email реальные, полоса опт-условий видна.

- [ ] **Step 5: Commit**

```bash
git add www/includes/config.php www/footer.php www/header.php www/index.php www/css/home.css
git commit -m "feat(home): единые контакты из config (без плейсхолдеров) + полоса опт-условий"
```

---

## Task 8: Кнопка «Запросить КП» (RFQ) → существующий лид-флоу

Кнопки «Запросить КП» на карточках и в hero открывают форму-заявку с предзаполненной позицией; сабмит идёт в существующий экшен `request_offer` (`includes/api.php`).

**Files:**
- Create: `www/js/rfq.js` (обработчик кнопок `.js-rfq`)
- Modify: `www/index.php` (подключить `rfq.js`; кнопка `.js-rfq` в `z_card()`)
- Modify: `www/includes/api.php` — только проверить/дополнить `handleRequestOffer()`, если не пишет `type='rfq'`

**Interfaces:**
- Consumes: экшен `POST /api.php?action=request_offer` (или существующий путь эндпоинта), `saveLeadToDB()`.
- Produces: лид с `type='rfq'`, `parameters={product_id,name,qty}`.

- [ ] **Step 1: Проверить существующий `handleRequestOffer()`**

Прочитать `handleRequestOffer()` в `includes/api.php`. Убедиться, что он: принимает `name/phone` + `parameters`, вызывает `saveLeadToDB()` с `type` (ожидается `'request_offer'`/`'rfq'`). Если `type` не проставляется — добавить `$data['type'] = 'rfq';` перед сохранением. Зафиксировать реальный URL эндпоинта (как его дергает существующий фронт — см. `js/script.js` OfferCart).

- [ ] **Step 2: Кнопка в карточке** — в `z_card()` (`index.php`) рядом с «В корзину» добавить:
```php
<button type="button" class="z-btn z-btn-ghost js-rfq" data-rfq="card" data-id="<?= (int)$r['id'] ?>" data-name="<?= htmlspecialchars($r['full_name']) ?>">Запросить КП</button>
```

- [ ] **Step 3: `www/js/rfq.js`** — делегированный обработчик: по клику `.js-rfq` скроллит к `#leadForm`, подставляет в скрытое/текстовое поле темы «Запрос КП: {name} (#{id})», проставляет `type=rfq`:
```js
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.js-rfq') : null;
    if (!b) return;
    e.preventDefault();
    var form = document.getElementById('leadForm');
    if (!form) return;
    var msg = form.querySelector('[name="message"], [name="comment"]');
    if (msg && b.dataset.id) msg.value = 'Запрос КП: ' + (b.dataset.name || '') + ' (#' + b.dataset.id + ')';
    var type = form.querySelector('[name="type"]');
    if (type) type.value = 'rfq';
    form.scrollIntoView({ behavior: 'smooth' });
    var name = form.querySelector('[name="name"]');
    if (name) name.focus();
  });
})();
```

- [ ] **Step 4: Подключить скрипт** — в `index.php` перед `</body>` добавить `<script src="/js/rfq.js" defer></script>`. Убедиться, что в `#leadForm` есть скрытое поле `<input type="hidden" name="type" value="contact_form">`.

- [ ] **Step 5: Проверка** — поднять сервер, нажать «Запросить КП» на карточке → страница скроллит к форме, поле сообщения предзаполнено «Запрос КП: …». Отправить с тестовым телефоном, проверить в БД:
```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -r "chdir('www'); require 'includes/config.php'; $d=getDbConnection(); var_dump($d->query(\"SELECT type,parameters FROM leads ORDER BY id DESC LIMIT 1\")->fetch());"
```
Expected: последняя запись `type='rfq'`.

- [ ] **Step 6: Commit**

```bash
git add www/js/rfq.js www/index.php www/includes/api.php
git commit -m "feat(home): «Запросить КП» (RFQ) на карточках/hero → лид type=rfq"
```

---

## Task 9: Быстрый заказ в 1 клик

Мини-форма (телефон + позиция) на карточке → лид `type='quick_order'` через `save_lead`, без корзины/регистрации.

**Files:**
- Modify: `www/js/rfq.js` (добавить обработчик `.js-quick`)
- Modify: `www/index.php` (кнопка `.js-quick` в `z_card()`; мини-модалка одного поля телефона)

**Interfaces:**
- Consumes: `POST /api.php?action=save_lead` (`handleSaveLead()` → `saveLeadToDB()`).
- Produces: лид `type='quick_order'`, `parameters={product_id,name,qty}`.

- [ ] **Step 1: Кнопка в карточке**
```php
<button type="button" class="z-btn z-btn-accent js-quick" data-id="<?= (int)$r['id'] ?>" data-name="<?= htmlspecialchars($r['full_name']) ?>" data-min="<?= (int)($r['min_order_qty'] ?? 1) ?>">Быстрый заказ</button>
```

- [ ] **Step 2: Мини-модалка** — один раз в `index.php` (скрытый блок): поле `tel` + кнопка «Заказать», контейнер `#quickModal` с `data-id` (проставляется JS).

- [ ] **Step 3: Обработчик в `rfq.js`** — по `.js-quick` открыть `#quickModal` с `data-id/name`; по «Заказать» — `fetch('/api.php?action=save_lead', {POST, body: {name:'Быстрый заказ', phone, type:'quick_order', parameters: JSON}})`; по `success` — тост «Заявка принята», закрыть модалку. Обязательно `.catch` (тост «Ошибка сети», как в `cart.js`).

- [ ] **Step 4: Проверка** — нажать «Быстрый заказ», ввести тестовый телефон, отправить. Проверить БД (как Task 8 Step 5): последняя запись `type='quick_order'` с `parameters.product_id`. Убедиться, что при обрыве сети показывается ошибка, не «висит».

- [ ] **Step 5: Commit**

```bash
git add www/js/rfq.js www/index.php
git commit -m "feat(home): быстрый заказ в 1 клик → лид type=quick_order"
```

---

## Task 10: Лид-магнит «Скачать прайс» (XLS из БД + захват контакта)

Кнопка «Скачать прайс» ведёт на `/price.php`: форма захвата (телефон/email) → лид `type='price_download'` → отдаётся XLS, собранный из актуальных цен/остатков через PhpSpreadsheet.

**Files:**
- Create: `www/includes/price_export.php` (чистая сборка строк прайса — тестируемо)
- Test: `www/tests/PriceExportTest.php`
- Create: `www/price.php` (страница: форма захвата + генерация/отдача XLS)

**Interfaces:**
- Produces: `price_rows(array $products, array $tiers): array` — матрица строк прайса (заголовок + строки товаров с ценами по уровням).
- Consumes: `Catalog::getProducts()`, `home_tiers()`, `saveLeadToDB()`, PhpSpreadsheet (`vendor/autoload.php`).

- [ ] **Step 1: Падающий тест на сборку строк**

```php
<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/home_view.php';
require_once __DIR__ . '/../includes/price_export.php';

class PriceExportTest extends TestCase
{
    public function testHeaderAndRowShape(): void
    {
        $tiers = [['label' => 'Опт', 'mult' => 0.9, 'class' => '']];
        $rows = price_rows([
            ['full_name' => 'Zip 25x30', 'width' => 250, 'height' => 300, 'thickness' => 40,
             'min_order_qty' => 100, 'stock_quantity' => 5000, 'price_rub' => 2.0],
        ], $tiers);
        $this->assertSame(['Наименование', 'Размер', 'Толщина, мкм', 'Мин. партия', 'Наличие', 'Опт'], $rows[0]);
        $this->assertSame('Zip 25x30', $rows[1][0]);
        $this->assertSame('25 × 30 см', $rows[1][1]);
        $this->assertSame('1,80', $rows[1][5]); // 2.0 * 0.9
    }

    public function testSkipsProductsWithoutPrice(): void
    {
        $rows = price_rows([['full_name' => 'X', 'price_rub' => 0]], [['label' => 'A', 'mult' => 1.0, 'class' => '']]);
        $this->assertCount(1, $rows); // только заголовок
    }
}
```

- [ ] **Step 2: Запустить — убедиться, что падает**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/PriceExportTest.php`
Expected: FAIL — `Failed opening required '.../includes/price_export.php'`.

- [ ] **Step 3: Реализовать `includes/price_export.php`**

```php
<?php
require_once __DIR__ . '/home_view.php';

/** Матрица строк прайса: [0] — заголовок, далее строки товаров (только с ценой). */
function price_rows(array $products, array $tiers): array {
    $header = ['Наименование', 'Размер', 'Толщина, мкм', 'Мин. партия', 'Наличие'];
    foreach ($tiers as $t) { $header[] = (string)$t['label']; }
    $rows = [$header];
    foreach ($products as $p) {
        $base = (float)($p['price_rub'] ?? 0);
        if ($base <= 0) { continue; }
        $row = [
            (string)($p['full_name'] ?? ''),
            home_size(isset($p['width']) ? (int)$p['width'] : null, isset($p['height']) ? (int)$p['height'] : null),
            $p['thickness'] !== null ? (string)(int)$p['thickness'] : '',
            (string)(int)($p['min_order_qty'] ?? 1),
            (int)($p['stock_quantity'] ?? 0) > 0 ? 'в наличии' : 'под заказ',
        ];
        foreach ($tiers as $t) { $row[] = home_price($base, (float)$t['mult']); }
        $rows[] = $row;
    }
    return $rows;
}
```

- [ ] **Step 4: Запустить — убедиться, что проходит**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/PriceExportTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Реализовать `www/price.php`**

Страница-эндпоинт. На GET — форма захвата (имя/телефон/email, согласие ПДн, CSRF, reCAPTCHA как на checkout). На POST — валидировать, `saveLeadToDB(['name'=>…, 'phone'=>…, 'email'=>…, 'type'=>'price_download', 'source'=>'price'])`, затем собрать XLS и отдать:
```php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/catalog_functions.php';
require_once __DIR__ . '/includes/price_export.php';
// ... после успешного сохранения лида:
$catalog = new Catalog();
$all = $catalog->getProducts(['sort' => 'popular'], 1, 1000)['products'];
$rows = price_rows($all, home_tiers());
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray($rows, null, 'A1');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="price-zippaket-' . date('Y-m-d') . '.xlsx"');
(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
exit;
```
Разметку формы оформить в тёмной теме (переиспользовать `.z-form`, `.z-consent` из существующих форм).

- [ ] **Step 6: Проверка вручную**

Поднять сервер, открыть `http://127.0.0.1:8077/price.php`, заполнить телефон+согласие, отправить. Ожидается: скачивается `price-zippaket-YYYY-MM-DD.xlsx` (открывается в Excel, есть заголовок и строки товаров с 3 колонками цен), а в `leads` появляется запись `type='price_download'` (проверка как Task 8 Step 5).

- [ ] **Step 7: Прогнать весь набор**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests`
Expected: `OK` (67 tests).

- [ ] **Step 8: Commit**

```bash
git add www/includes/price_export.php www/tests/PriceExportTest.php www/price.php
git commit -m "feat(home): лид-магнит «Скачать прайс» — XLS из БД + захват контакта (type=price_download)"
```

---

## Task 11: Регрессия и финальный прогон Фазы A

**Files:** — (проверки; при находках — точечные фиксы)

- [ ] **Step 1: Все тесты**

Run из `www/`: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests`
Expected: `OK` (67 tests).

- [ ] **Step 2: Ключевые страницы отдают 200** (сервер на 8077): `/`, `/katalog_zip_paketov/`, `/katalog_zip_paketov/?search=25x30&type=slider`, `/product/1`, `/price.php`, `/cart.php`, `/checkout.php`, `/sitemap.xml`.

- [ ] **Step 3: Порядок секций главной** — визуально сверить сверху вниз: hero(оффер+поиск+2 CTA) → опт-условия → Хиты → Новинки → калькулятор → (доверие — Фаза B) → контакты/форма → FAQ(Фаза B) → футер.

- [ ] **Step 4: Лид-механики** — «Запросить КП», «Быстрый заказ», «Скачать прайс» создают лиды с корректными `type`. Плейсхолдеров контактов нет (`rg -n "8 \(800\) 123-45-67|info@zip" www/` → пусто).

- [ ] **Step 5: Скриншоты** desktop+mobile главной (`zshots\shoot.py`) — приложить к PR как «до/после».

- [ ] **Step 6: Финальный commit**

```bash
git add -A
git commit -m "test(home): регрессионный прогон витрины Фазы A"
```

---

## Что НЕ входит в Фазу A (следующие планы)

- **Фаза B (доверие):** кейсы/лого → таблица `reviews`+CRUD, FAQ + FAQPage schema, блок гарантий/документов, виджет мессенджеров.
- **Фаза C (SEO/перф):** canonical, мета+OG на статике, Product JSON-LD (stock/rating), схема в листинге, WebP/srcset для hero, font-display swap, минификация CSS, LocalBusiness, фикс robots.txt.
- **Фаза D:** брошенная корзина-догон, сравнение, подписка на прайс, личный кабинет (+персональные цены).
- Реальные фото товаров и галерея — при поступлении снимков (решение владельца: пока плейсхолдеры).
- PDF-версия прайса (нет PDF-библиотеки в проекте) — опционально позже; сейчас XLS через PhpSpreadsheet.
```
