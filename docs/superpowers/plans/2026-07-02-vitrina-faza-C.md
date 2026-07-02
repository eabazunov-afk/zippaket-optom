# Витрина — Фаза C (SEO / производительность) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Закрыть SEO/перф-пробелы витрины по стандартам 2026: canonical везде, уникальные meta description + Open Graph на статических страницах, обогащённый Product JSON-LD с `aggregateRating` из отзывов Фазы B (rich-сниппеты со звёздами), микроразметка листинга, LocalBusiness, починка robots.txt и базовая перф-гигиена (LCP/CLS hero).

**Architecture:** Микроразметка и мета собираются из существующих данных (`Catalog`, новый агрегат рейтинга в `includes/reviews.php`). Чистые функции (формат рейтинга, сборка JSON-LD) — в модулях с PHPUnit. Мета статических страниц — через расширение `includes/page_head.php` (страницы задают `$pageDescription`/`$pageCanonical`). Никаких новых зависимостей; тёмная тема и токены не трогаются (SEO — это `<head>`/schema, не визуал).

**Tech Stack:** PHP 8.3, MySQL 8.x (PDO), PHPUnit 9.6. Schema.org JSON-LD. Без сборщиков.

## Global Constraints

- PHP путь: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`. Тесты из `www/`: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → `OK`. База — 72 теста (после Фазы B).
- Чистые функции тестируются (без БД в тестах). JSON-LD валиден (декодируется, корректные `@type`).
- Домен: `https://zippaket-optom.ru`. Название: `ZLOCK`. Телефон/адрес — из констант `config.php` (`SUPPORT_PHONE`/`SELLER_*`), НЕ хардкод.
- **Единый стиль (гейт):** визуальную вёрстку не меняем; если добавляется разметка с текстом — только токены `--z-*`, ноль сырых hex. Большинство задач — `<head>`/`<script type=ld+json>`, визуала не касаются.
- Не ломать существующее: `product.php` уже имеет canonical + Product JSON-LD с Offer/availability; `header.php` шрифты уже с `&display=swap`. Дополняем, не дублируем.
- Schema-политика: `aggregateRating` выводить ТОЛЬКО при наличии реальных одобренных отзывов (count>0) — иначе Google штрафует за пустой rating. Никаких выдуманных рейтингов (антипаттерн).
- `www/includes/config.php` gitignored — новые константы в `config.example.php` + дефолт.
- Тексты meta — русские, деловой опт, уникальные на страницу, ≤160 симв. для description.

---

## Task 1: Агрегат рейтинга отзывов (`includes/reviews.php`)

Для `AggregateRating` нужен средний балл и число одобренных отзывов (общий и по товару).

**Files:**
- Modify: `www/includes/reviews.php` (добавить чистый форматтер + DB-агрегат)
- Test: `www/tests/ReviewsTest.php` (добавить тест форматтера)

**Interfaces:**
- Produces (чистая, тестируемая): `rating_round(float $avg): string` — округление до 1 знака, точка-десятичный, «4.7»; пустая строка если ≤0.
- Produces (БД, без теста): `reviews_rating_aggregate(?int $productId = null): array` — `['count'=>int, 'avg'=>float]` по одобренным (`is_approved=1`), опц. по товару.

- [ ] **Step 1: Тест форматтера** — добавить в `ReviewsTest`:

```php
    public function testRatingRound(): void
    {
        $this->assertSame('4.7', rating_round(4.6667));
        $this->assertSame('5.0', rating_round(5.0));
        $this->assertSame('', rating_round(0.0));
    }
```

- [ ] **Step 2: Запустить — FAIL** (`undefined function rating_round`).
Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/ReviewsTest.php`

- [ ] **Step 3: Реализовать в `includes/reviews.php`** (в конец файла):

```php
/** Средний балл → строка "4.7" (1 знак, точка). Пусто если ≤0. */
function rating_round(float $avg): string {
    if ($avg <= 0) { return ''; }
    return number_format($avg, 1, '.', '');
}

/** Агрегат одобренных отзывов: ['count'=>int, 'avg'=>float]. Опц. по товару. */
function reviews_rating_aggregate(?int $productId = null): array {
    try {
        $db = getDbConnection();
        if ($productId !== null) {
            $stmt = $db->prepare("SELECT COUNT(*) c, COALESCE(AVG(rating),0) a FROM reviews WHERE is_approved=1 AND product_id=?");
            $stmt->execute([$productId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) c, COALESCE(AVG(rating),0) a FROM reviews WHERE is_approved=1");
        }
        $row = $stmt->fetch();
        return ['count' => (int)($row['c'] ?? 0), 'avg' => (float)($row['a'] ?? 0)];
    } catch (PDOException $e) { error_log('reviews_rating_aggregate: ' . $e->getMessage()); return ['count' => 0, 'avg' => 0.0]; }
}
```

- [ ] **Step 4: Запустить — PASS.** Затем весь набор:
Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests`
Expected: `OK` (72 + 1 = 73 tests).

- [ ] **Step 5: Commit**

```bash
git add www/includes/reviews.php www/tests/ReviewsTest.php
git commit -m "feat(reviews): агрегат рейтинга (rating_round + reviews_rating_aggregate) для schema"
```

---

## Task 2: `aggregateRating` + `url` в Product JSON-LD (`product.php`)

Обогатить существующий Product JSON-LD рейтингом из отзывов Фазы B → звёзды в выдаче.

**Files:**
- Modify: `www/product.php` (блок Product JSON-LD, ~строки 55-70)

**Interfaces:**
- Consumes: `reviews_rating_aggregate((int)$product['id'])`, `rating_round()`.

- [ ] **Step 1: Подключить** — убедиться, что `require_once __DIR__ . '/includes/reviews.php';` есть в `product.php` (добавить, если нет).

- [ ] **Step 2: В сборке Product JSON-LD** добавить `url` и условный `aggregateRating`:

```php
$agg = reviews_rating_aggregate((int)$product['id']);
$productLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product['full_name'],
    'url' => 'https://zippaket-optom.ru/product/' . (int)$product['id'],
    // ... existing image/description/offers ...
];
if ($agg['count'] > 0) {
    $productLd['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => rating_round($agg['avg']),
        'reviewCount' => $agg['count'],
    ];
}
```
(Сохранить существующие поля offers/availability. Не выводить `aggregateRating` при `count=0` — см. Global Constraints.)

- [ ] **Step 3: Проверка** — `php -l product.php` clean. Сервер: у товара с отзывами (если есть) в исходнике JSON-LD присутствует `aggregateRating`; у товара без отзывов — отсутствует (не пустой). `curl -s http://127.0.0.1:8077/product/1 | grep -c aggregateRating` → 0 или 1 (в зависимости от наличия одобренных отзывов по товару). Валидность: скопировать JSON-LD в validator.schema.org (ручная проверка).

- [ ] **Step 4: Commit**

```bash
git add www/product.php
git commit -m "feat(seo): Product JSON-LD — aggregateRating из отзывов + url (rich-сниппеты)"
```

---

## Task 3: canonical на главной

**Files:**
- Modify: `www/index.php` (`<head>`, рядом с og:url ~строка 133)

- [ ] **Step 1: Добавить** после мета-тегов в `<head>`:

```php
<link rel="canonical" href="https://zippaket-optom.ru/">
```

- [ ] **Step 2: Проверка** — `php -l index.php`; `curl -s http://127.0.0.1:8077/ | grep -c 'rel="canonical"'` → 1.

- [ ] **Step 3: Commit**

```bash
git add www/index.php
git commit -m "feat(seo): canonical на главной"
```

---

## Task 4: Мета/OG/canonical на статических страницах (`page_head.php`)

**Files:**
- Modify: `www/includes/page_head.php` (поддержать `$pageDescription`, `$pageCanonical`, OG, robots)
- Modify: `www/kontakty.php`, `www/dostavka-i-oplata.php`, `www/oferta.php`, `www/vozvrat.php`, `www/cookie-policy.php` (задать `$pageDescription` + `$pageCanonical` перед include page_head), `www/404.php` (robots noindex)

**Interfaces:**
- Consumes: опциональные `$pageDescription`, `$pageCanonical`, `$pageRobots` из включающей страницы.

- [ ] **Step 1: Расширить `page_head.php`** — в `<head>` после `<title>` добавить:

```php
    <?php if (!empty($pageDescription)): ?><meta name="description" content="<?= htmlspecialchars($pageDescription) ?>"><?php endif; ?>
    <meta name="robots" content="<?= htmlspecialchars($pageRobots ?? 'index, follow') ?>">
    <?php if (!empty($pageCanonical)): ?><link rel="canonical" href="<?= htmlspecialchars($pageCanonical) ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?> | ZLOCK">
    <?php if (!empty($pageDescription)): ?><meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>"><?php endif; ?>
    <?php if (!empty($pageCanonical)): ?><meta property="og:url" content="<?= htmlspecialchars($pageCanonical) ?>"><?php endif; ?>
```

- [ ] **Step 2: Задать переменные на каждой странице** — перед `require .../page_head.php` (или где определяется `$pageTitle`) добавить `$pageDescription` (уникальный, ≤160 симв., деловой опт) и `$pageCanonical` (полный URL страницы). Примеры:
  - `kontakty.php`: описание про контакты/реквизиты опт-поставщика ZIP-пакетов; canonical `https://zippaket-optom.ru/kontakty`.
  - `dostavka-i-oplata.php`: условия доставки по РФ и оплаты (карта/счёт/НДС); canonical соответствующий.
  - `oferta.php`, `vozvrat.php`, `cookie-policy.php`: краткие уникальные описания + canonical.
  - `404.php`: `$pageRobots = 'noindex, follow';` (не индексировать).
  Точные ЧПУ-адреса свериться с `.htaccess`.

- [ ] **Step 3: Проверка** — `php -l` по всем изменённым; для каждой статической страницы `curl` показывает уникальный `<meta name="description">`, `canonical`, `og:*`; на `/404` — `robots noindex`. Grep, что описания различаются между страницами.

- [ ] **Step 4: Commit**

```bash
git add www/includes/page_head.php www/kontakty.php www/dostavka-i-oplata.php www/oferta.php www/vozvrat.php www/cookie-policy.php www/404.php
git commit -m "feat(seo): уникальные meta description/canonical/OG на статических страницах"
```

---

## Task 5: Микроразметка листинга каталога (ItemList)

Товарные карточки листинга — в structured data, чтобы каталожные страницы попадали в rich-результаты.

**Files:**
- Create: `www/includes/catalog_schema.php` (`catalog_itemlist_jsonld(array $products): string` — чистая, тестируется)
- Test: `www/tests/CatalogSchemaTest.php`
- Modify: `www/katalog_zip_paketov/katalog.php` (вывести JSON-LD один раз)

**Interfaces:**
- Produces: `catalog_itemlist_jsonld(array $products): string` — `<script ld+json>` с `@type:ItemList`, `itemListElement[]` = `{@type:ListItem, position, url, name}`.

- [ ] **Step 1: Падающий тест**

```php
<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/catalog_schema.php';

class CatalogSchemaTest extends TestCase
{
    public function testItemListStructure(): void
    {
        $out = catalog_itemlist_jsonld([
            ['id' => 42, 'full_name' => 'Zip 25x30'],
            ['id' => 7,  'full_name' => 'Слайдер 30x40'],
        ]);
        $json = json_decode(preg_replace('#</?script[^>]*>#', '', $out), true);
        $this->assertSame('ItemList', $json['@type']);
        $this->assertSame(1, $json['itemListElement'][0]['position']);
        $this->assertStringContainsString('/product/42', $json['itemListElement'][0]['url']);
        $this->assertSame('Zip 25x30', $json['itemListElement'][0]['name']);
        $this->assertSame(2, $json['itemListElement'][1]['position']);
    }
}
```

- [ ] **Step 2: Запустить — FAIL.**
Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/CatalogSchemaTest.php`

- [ ] **Step 3: Реализовать `includes/catalog_schema.php`**

```php
<?php
/** ItemList JSON-LD из списка товаров каталога. Чистая. */
function catalog_itemlist_jsonld(array $products): string {
    $items = [];
    $pos = 1;
    foreach ($products as $p) {
        $id = (int)($p['id'] ?? 0);
        if ($id <= 0) { continue; }
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'url' => 'https://zippaket-optom.ru/product/' . $id,
            'name' => (string)($p['full_name'] ?? ''),
        ];
    }
    $data = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items];
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}
```

- [ ] **Step 4: Запустить — PASS.** Затем весь набор → `OK` (73 + 1 = 74 tests).

- [ ] **Step 5: Вывести в каталоге** — `katalog.php`: `require_once __DIR__ . '/../includes/catalog_schema.php';` и в `<head>`/перед листингом `<?= catalog_itemlist_jsonld($result['products']) ?>` (переменная с товарами страницы — свериться с реальным именем в katalog.php). Один раз.

- [ ] **Step 6: Проверка** — `php -l katalog.php`; `curl -s '.../katalog_zip_paketov/' | grep -c '"@type":"ItemList"'` → 1; позиции идут по порядку.

- [ ] **Step 7: Commit**

```bash
git add www/includes/catalog_schema.php www/tests/CatalogSchemaTest.php www/katalog_zip_paketov/katalog.php
git commit -m "feat(seo): ItemList JSON-LD для листинга каталога + тест"
```

---

## Task 6: LocalBusiness + фикс robots.txt

**Files:**
- Modify: `www/index.php` (расширить Organization → добавить адрес/контакты; или отдельный LocalBusiness JSON-LD)
- Modify: `www/robots.txt` (убрать противоречие `Disallow /*.php$` vs `Allow /*.php?$`)

- [ ] **Step 1: Organization → контактные данные** — в существующий Organization JSON-LD на главной добавить (из констант, без хардкода реальных данных, если их нет — использовать `SELLER_ADDRESS`/`SUPPORT_PHONE`/`ADMIN_EMAIL`; поля с плейсхолдерами не выводить):

```php
'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'RU', 'addressLocality' => '<город из SELLER_ADDRESS если задан>'],
'contactPoint' => ['@type' => 'ContactPoint', 'telephone' => SUPPORT_PHONE, 'contactType' => 'sales', 'areaServed' => 'RU', 'availableLanguage' => 'Russian'],
```
Если реальный адрес неизвестен (плейсхолдер `SELLER_*`) — адрес НЕ добавлять, оставить contactPoint. Помечено: владелец даёт реальный адрес → тогда включить полноценный LocalBusiness.

- [ ] **Step 2: robots.txt** — удалить противоречивые строки `Disallow: /*.php$`, `Allow: /*.php?$`, `Allow: /*.html?$` (URL сайта — ЧПУ без `.php`; правила лишние и конфликтуют). Оставить `Disallow: /admin/`, `/includes/`, `/private/`, `/*.sql$`, `Sitemap:`, `Host:`, `Clean-param`. Проверить, что `/product/…`, каталог, юр-страницы остаются разрешёнными.

- [ ] **Step 3: Проверка** — `php -l index.php`; `curl -s http://127.0.0.1:8077/ | grep -c contactPoint` ≥1; `curl -s http://127.0.0.1:8077/robots.txt` — без строк `/*.php$`. Убедиться, что sitemap.xml и юр-страницы не под Disallow.

- [ ] **Step 4: Commit**

```bash
git add www/index.php www/robots.txt
git commit -m "feat(seo): контактные данные в Organization + фикс противоречия robots.txt"
```

---

## Task 7: Перф-гигиена hero (LCP/CLS)

Без пересборки картинок (WebP-конвертация — отдельная asset-задача владельца). Снижаем LCP/CLS дешёвыми средствами.

**Files:**
- Modify: `www/index.php` (hero `<img>` + preload), `www/header.php` (defer некритичного JS, если есть)

- [ ] **Step 1: Hero-изображение** — у главного hero `<img>` проставить `width`/`height` (реальные пропорции) для резерва места (против CLS), `fetchpriority="high"` и `loading="eager"` (это LCP — НЕ lazy). Прочие изображения ниже сгиба — `loading="lazy"` (проверить, что уже так).

- [ ] **Step 2: Preload LCP** — в `<head>` (после шрифтов) добавить `<link rel="preload" as="image" href="<путь-hero>" fetchpriority="high">` для hero-картинки.

- [ ] **Step 3: JS `defer`** — проверить подключения `<script src=...>` в `header.php`/`index.php`; некритичные (не влияющие на первый рендер) пометить `defer`. reCAPTCHA уже async. Не ломать порядок инициализации (home.js/cart.js/rfq.js).

- [ ] **Step 4: Проверка** — `php -l`; сервер: hero-img имеет width/height + fetchpriority; preload присутствует; страница рендерится без визуальных регрессий (скриншот при возможности). Тесты 74 OK.

- [ ] **Step 5: Commit**

```bash
git add www/index.php www/header.php
git commit -m "perf(home): hero LCP/CLS — width/height, preload, fetchpriority, defer JS"
```

---

## Task 8: Регрессия и финальный прогон Фазы C

**Files:** — (проверки; при находках — точечные фиксы)

- [ ] **Step 1: Все тесты** — `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → `OK` (74 tests).
- [ ] **Step 2: `php -l`** по изменённым PHP — чисто.
- [ ] **Step 3: Микроразметка** — на `/` один Organization (+FAQPage из Фазы B), на `/product/N` — Product (+aggregateRating при отзывах), на каталоге — ItemList. Все декодируются как валидный JSON. Дублей нет.
- [ ] **Step 4: Мета** — canonical на `/`, `/product/N`, статических; уникальные description на статических; `/404` noindex. `robots.txt` без противоречий, sitemap доступен.
- [ ] **Step 5: Страницы 200** — `/`, `/product/1`, каталог, `/kontakty`, `/oferta`, `/dostavka-i-oplata`, `/review_add.php`.
- [ ] **Step 6: Финальный commit** — `git commit --allow-empty -m "test(seo): регрессионный прогон Фазы C"`.

---

## Что НЕ входит в Фазу C (следующее)

- **WebP/AVIF-конвертация изображений** (папка images 42 МБ) + `<picture>`/`srcset` с реальными размерами — asset-задача (нужны исходники/пайплайн; при поступлении реальных фото товаров).
- **Минификация/объединение CSS** (185 КБ, легаси style.css 78 КБ) — уместнее в фазе единой дизайн-системы (тогда легаси выводится из игры).
- **Единая СВЕТЛАЯ дизайн-система** (после C): ремап токенов тёмная→светлая, свод 3 систем к одной, ~390 сырых hex, единые иконки.
- **Sitemap image-расширение**, hreflang, slug вместо id в URL — backlog.
- **Фаза D:** брошенная корзина, сравнение, подписка, личный кабинет.
- Реальный адрес компании для полноценного LocalBusiness — от владельца.
