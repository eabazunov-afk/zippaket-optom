# Светлая дизайн-система A3 «Бронза» — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Перевести публичную витрину zippaket-optom.ru с тёмной темы на светлую A3 «Бронза / тихая роскошь» и свести три конкурирующие системы токенов в одну — преимущественно ремапом значений `--z-*`, на которых уже построены Фазы A/B/C.

**Architecture:** Ядро — ремап `--z-*` в `:root` (`css/site-premium.css`): разметка, использующая `var(--z-*)`, флипается автоматически. Затем гасим источники «остаточной тёмной»: `shop-dark.css` (дарк-оверрайд магазина), легаси-цвета `style.css`, `--pm-*` (`premium.css`) и «сырые» hex. Отдельно — типографика (Fraunces), тёмные акцент-панели на новых ink-токенах, бронзовый логотип, иконки Phosphor. Верификация — рендер на dev-сервере + грепы + скриншоты; юнит-тесты для CSS отсутствуют, PHPUnit держим зелёным как страховку логики.

**Tech Stack:** PHP 8.3, ванильный CSS (кастомные свойства), Google Fonts (Fraunces + Plus Jakarta Sans), Phosphor icons. Без новых зависимостей.

## Global Constraints

- PHP путь: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`. Dev-сервер (из корня): `& "C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" -S 127.0.0.1:8077 -t www router.php` (в фоне).
- **Ветка стека:** `feature/light-design-system` (на базе `feature/vitrina-faza-C` = A+B+C). Все `--z-*` использования Фаз A/B/C присутствуют.
- **Единственная система цветов витрины — `--z-*`.** Имена токенов НЕ переименовываем (от этого флипается разметка). Значения — из таблицы спеки.
- **Ноль новых «сырых» hex** в правках: любой цвет — через `var(--z-*)`. Существующий легаси-hex либо ремапим к токенам, либо к светлым значениям.
- Тёмные «акцент-моменты» (полосы доверия, финальный CTA, футер) — на новых ink-токенах `--z-ink*`, НЕ на `--z-bg-2` (он теперь светлый).
- Доступность: текст-ссылки/мелкий текст — на `--z-mint-2` (`#7C6122`, контраст AA), не на светлой бронзе `--z-mint`.
- PHPUnit из `www/`: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → `OK (74 tests)` после каждой задачи (логику не трогаем).
- Область — только публичная витрина. Админку (`admin/*`), WebP/фото, полную зачистку `style.css` НЕ трогаем.
- Каждая задача завершается коммитом; при незавершённой перекраске промежуточный «микс тёмное/светлое» на ветке допустим — финальная задача сводит к когерентному светлому.

### Палитра A3 (значения для всех задач — единый источник)

```
--z-bg:#F4F2EC; --z-bg-2:#FBFAF6; --z-surface:#FFFFFF; --z-surface-2:#F3EFE3;
--z-hairline:rgba(26,23,18,.11); --z-hairline-strong:rgba(26,23,18,.18);
--z-text:#1A1712; --z-text-2:#5B5347; --z-text-3:#948B7B;
--z-mint:#9A7B2E; --z-mint-2:#7C6122; --z-mint-deep:#C6A24E;
--z-gold:#C6A24E; --z-gold-2:#9A7B2E; --z-tg:#1B8FC4;
--z-ink:#1A1712; --z-ink-2:#2A2620; --z-on-ink:#EDE8DD; --z-on-ink-2:#A99E88; --z-on-ink-acc:#D8B65E;
--font-display:'Fraunces',Georgia,serif; --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
```

---

## Task 1: Ремап `--z-*` в `:root` на светлые A3 + ink-токены + шрифт-переменные

Ядро флипа. Меняем значения токенов в `site-premium.css`; разметка на `var(--z-*)` перекрашивается.

**Files:**
- Modify: `www/css/site-premium.css` (блок `:root`)

- [ ] **Step 1: Найти текущий `:root`** — `rg -n ":root" www/css/site-premium.css`. Прочитать блок (текущие тёмные `--z-*`, тени, шрифты).

- [ ] **Step 2: Заменить значения `--z-*` на светлые A3** (имена сохранить). Пример итогового `:root` (адаптировать под реальные имена в файле — если токена из таблицы нет, добавить; лишние тёмные оттенки-варианты перекрасить в тон):

```css
:root{
  --z-bg:#F4F2EC; --z-bg-2:#FBFAF6;
  --z-surface:#FFFFFF; --z-surface-2:#F3EFE3;
  --z-hairline:rgba(26,23,18,.11); --z-hairline-strong:rgba(26,23,18,.18);
  --z-text:#1A1712; --z-text-2:#5B5347; --z-text-3:#948B7B;
  --z-mint:#9A7B2E; --z-mint-2:#7C6122; --z-mint-deep:#C6A24E;
  --z-gold:#C6A24E; --z-gold-2:#9A7B2E; --z-tg:#1B8FC4;
  --z-ink:#1A1712; --z-ink-2:#2A2620; --z-on-ink:#EDE8DD; --z-on-ink-2:#A99E88; --z-on-ink-acc:#D8B65E;
  --z-shadow-sm:0 1px 2px rgba(26,23,18,.05);
  --z-shadow:0 1px 2px rgba(26,23,18,.05), 0 12px 30px rgba(26,23,18,.07);
  --z-shadow-lg:0 20px 50px rgba(26,23,18,.12);
  --z-glow-mint:0 12px 30px rgba(154,123,46,.22);
  --z-glow-gold:0 12px 30px rgba(198,162,78,.24);
  --z-radius:16px; --z-radius-sm:12px; --z-pill:30px;
  --font-display:'Fraunces',Georgia,serif; --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
  /* прочие существующие --z-* переменные (ease и т.п.) — оставить как есть */
}
```
Если `body`/глобальный фон в site-premium.css задан `background:var(--z-bg)` с `background-attachment:fixed` тёмным градиентом — заменить на плоскую бумагу `var(--z-bg)`.

- [ ] **Step 3: Рендер-проверка** — поднять dev-сервер, открыть `/`. Ожидается: фон стал светлым (бумага), текст тёмным, кнопки/акценты бронзовыми. Части на `var(--z-*)` — светлые; части на легаси/hex (шапка из style.css, магазин из shop-dark.css) пока могут остаться тёмными — это ок, чиним дальше.
- [ ] **Step 4: Тесты-страховка** — `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → `OK (74)`.
- [ ] **Step 5: Commit**

```bash
git add www/css/site-premium.css
git commit -m "design(tokens): ремап --z-* тёмная→светлая A3 (бумага/ink/бронза) + ink-панель токены"
```

---

## Task 2: Типографика — Fraunces + компактный масштаб

**Files:**
- Modify: `www/header.php` (Google Fonts: добавить Fraunces, убрать/заменить Space Grotesk)
- Modify: `www/css/home.css`, `www/css/site-premium.css` (масштаб заголовков)

- [ ] **Step 1: Шрифт в `header.php`** — найти `<link ...fonts.googleapis...>` (`rg -n "fonts.googleapis" www/header.php`). Заменить строку подключения на набор с Fraunces + Plus Jakarta Sans (Space Grotesk убрать, если больше нигде не нужен — иначе оставить):

```html
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,ital,wght@9..144,0,400;9..144,0,600;9..144,1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
```

- [ ] **Step 2: Масштаб заголовков** — там, где заголовки заданы фиксированным крупным размером (hero h1, `.z-h1/.z-h2`, `.z-hero-title`), привести к компактной шкале: hero `clamp(30px,4vw,38px)`, секции `24px`, карточки `16–17px`, эйрброу `11px`. Использовать `font-family:var(--font-display)` (Fraunces) для дисплейных, `var(--font-body)` для текста. Найти цели: `rg -n "font-size" www/css/home.css | rg -n "3[0-9]px|4[0-9]px|5[0-9]px"`.
- [ ] **Step 3: Цены/цифры** — убедиться, что блоки цен/остатков имеют `font-variant-numeric:tabular-nums` (добавить, если нет).
- [ ] **Step 4: Проверка** — рендер `/`: заголовки антиквой Fraunces, масштаб компактный (не «плакатный»). `rg -n "Space Grotesk" www/` → пусто или только там, где осознанно оставили.
- [ ] **Step 5: Тесты** → `OK (74)`. **Commit**

```bash
git add www/header.php www/css/home.css www/css/site-premium.css
git commit -m "design(type): Fraunces + компактная типографическая шкала"
```

---

## Task 3: Тёмные акцент-панели на ink-токенах

Полосы доверия/финальный CTA/футер должны остаться тёмными на светлом сайте. Сейчас часть из них использует `--z-bg-2`/`--z-surface` (стали светлыми) или `#16181C`/`--z-ink` из разметки Фаз A/B.

**Files:**
- Modify: `www/index.php` (полоса доверия/кейсы-трест — уже частично на `#16181C`/`--z-ink`), `www/footer.php`, `www/css/site-premium.css`/`www/css/home.css` (классы `.z-trust`, футер)

- [ ] **Step 1: Найти тёмные панели** — `rg -n "16181C|z-trust|z-ink|trust\b" www/index.php www/footer.php www/css/*.css`.
- [ ] **Step 2: Перевести на ink-токены** — фон таких панелей → `var(--z-ink)`, текст → `var(--z-on-ink)`, вторичный → `var(--z-on-ink-2)`, акцент-цифры → `var(--z-on-ink-acc)`. Заменить любые прямые `#16181C`/`#fff` внутри этих панелей на ink-токены. Футер (`footer.php`) — тёмная ink-панель на светлом сайте (осознанно): фон `var(--z-ink)`, текст `var(--z-on-ink)`.
- [ ] **Step 3: Проверка** — рендер `/`: полоса доверия и футер — тёмные ink-панели с бронзовыми акцентами на светлом фоне; контраст читаем. `rg -n "#16181C|#0A1422" www/index.php www/footer.php` → пусто (заменено на токены).
- [ ] **Step 4: Тесты** → `OK (74)`. **Commit**

```bash
git add www/index.php www/footer.php www/css/site-premium.css www/css/home.css
git commit -m "design(panels): тёмные акцент-панели (доверие/футер) на ink-токенах"
```

---

## Task 4: `shop-dark.css` → светлый ремап (каталог/товар/корзина/checkout)

`shop-dark.css` (22 КБ) — дарк-оверрайд магазина; на светлой теме он держит магазин тёмным. Ремапим его цвета к светлым токенам.

**Files:**
- Modify: `www/css/shop-dark.css`

- [ ] **Step 1: Аудит** — `rg -n "#[0-9a-fA-F]{3,6}|--pm-|background|color" www/css/shop-dark.css | head -60`. Понять, что файл переопределяет (обычно `--pm-*` → тёмные, тёмные фоны/текст).
- [ ] **Step 2: Ремап правил** — тёмные фоны → `var(--z-bg)`/`var(--z-surface)`; тёмные поверхности → `var(--z-surface)`/`var(--z-surface-2)`; светлый текст (`#EAF2FB`/`#fff`) → `var(--z-text)`/`var(--z-text-2)`; границы → `var(--z-hairline)`; акценты → `var(--z-mint)`. Если файл маппит `--pm-*` в тёмные значения — перенаправить на светлые токены. Правило: ни одного тёмного фона под текст на светлой витрине.
- [ ] **Step 3: Рассмотреть переименование** — если после ремапа `shop-dark.css` почти дублирует light-токены, оставить как тонкий override; переименование файла — НЕ в этой задаче (ссылки в `page_head.php`/`product.php`/`checkout.php` не трогаем, чтобы не плодить правки).
- [ ] **Step 4: Проверка** — рендер `/katalog_zip_paketov/`, `/product/1`, `/cart.php`, `/checkout.php`: страницы светлые, карточки/фильтры/формы на бумаге, текст тёмный, читаемо. `rg -n "#070E18|#0A1422|#16181C|#EAF2FB" www/css/shop-dark.css` → пусто.
- [ ] **Step 5: Тесты** → `OK (74)`. **Commit**

```bash
git add www/css/shop-dark.css
git commit -m "design(shop): ремап shop-dark.css на светлые токены (каталог/товар/корзина/checkout)"
```

---

## Task 5: Легаси `style.css` — нейтрализация цветов

`style.css` (78 КБ, `--primary`/hex) стилизует шапку/футер/калькулятор/cookie/модалки. Цвета нейтрализуем к токенам; мёртвые правила — безопасно тримим.

**Files:**
- Modify: `www/css/style.css`

- [ ] **Step 1: Аудит цветовых переменных** — `rg -n "var\(--primary|--primary|--secondary|--dark|--light|#[0-9a-fA-F]{6}" www/css/style.css | head -80`. Составить карту: какие `--primary*`/hex отвечают за фон/текст/акцент шапки-футера-калькулятора.
- [ ] **Step 2: Привязать легаси-переменные к `--z-*`** — в начале `style.css` (или в `:root`) переопределить легаси-цвета через токены, а не менять сотни точечных правил:

```css
:root{
  --primary: var(--z-mint);
  --primary-dark: var(--z-mint-2);
  --secondary: var(--z-text-2);
  --dark: var(--z-text);
  --light: var(--z-bg);
  /* добавить остальные легаси-цветовые переменные, найденные в Step 1, → к токенам */
}
```
Это флипает большинство легаси-правил без правки каждой. Затем точечно пройти оставшиеся «сырые» тёмные hex под текст/фон (шапка/футер), заменив на `var(--z-*)`.

- [ ] **Step 3: Тёмная шапка/футер из легаси** — если `style.css` жёстко задаёт тёмный фон шапки/футера, синхронизировать с решением Task 3 (шапка светлая `var(--z-bg-2)`, футер тёмный `var(--z-ink)`).
- [ ] **Step 4: Безопасный трим** — удалить только очевидно мёртвые/дублирующие блоки, если наткнулись (не обязательный шаг; полная зачистка — вне области).
- [ ] **Step 5: Проверка** — рендер всех типов страниц: шапка/футер/калькулятор/cookie-баннер/модалки — светлые и когерентные, без тёмных «дыр». Открыть калькулятор и cookie-баннер, проверить читаемость.
- [ ] **Step 6: Тесты** → `OK (74)`. **Commit**

```bash
git add www/css/style.css
git commit -m "design(legacy): нейтрализация цветов style.css через токены --z-*"
```

---

## Task 6: `premium.css` (`--pm-*`) → свод к `--z-*`

**Files:**
- Modify: `www/css/premium.css`, при необходимости `www/css/home-premium.css`

- [ ] **Step 1: Аудит** — `rg -n "--pm-" www/css/premium.css www/css/home-premium.css`. Составить соответствие `--pm-*` → `--z-*`.
- [ ] **Step 2: Свести** — в `:root` `premium.css` переопределить `--pm-*` через токены (`--pm-bg:var(--z-bg)` и т.д.), либо заменить использования на `--z-*`. Цель: `--pm-*` больше не несёт собственных тёмных значений.
- [ ] **Step 3: Проверка** — рендер страниц, использующих премиум-компоненты (`.z-glass`, `.z-lift` и пр.): светлые, консистентные. `rg -n "#0[0-9a-fA-F]{5}" www/css/premium.css` (тёмные hex) → пусто/нейтрализовано.
- [ ] **Step 4: Тесты** → `OK (74)`. **Commit**

```bash
git add www/css/premium.css www/css/home-premium.css
git commit -m "design(tokens): свод --pm-* к --z-* (premium.css)"
```

---

## Task 7: Сырые hex в `home.css` / `catalog.css` → токены/светлые

**Files:**
- Modify: `www/css/home.css`, `www/css/catalog.css`

- [ ] **Step 1: Найти сырые hex** — `rg -n "#[0-9a-fA-F]{3,6}" www/css/home.css www/css/catalog.css`.
- [ ] **Step 2: Заменить** — цвета текста/фона/границ/акцентов → соответствующие `var(--z-*)`. Тёмные значения, оставшиеся от тёмной темы, привести к светлым токенам. Нейтральные утилитарные (напр. `#fff` на белой карточке) → `var(--z-surface)`; акценты → `var(--z-mint)`.
- [ ] **Step 3: Проверка** — рендер `/` и каталога: ни одного «выбивающегося» тёмного элемента. `rg -n "#[0-9a-fA-F]{6}" www/css/home.css www/css/catalog.css` — остались только осознанные (или пусто). Зафиксировать список осознанных исключений в отчёте.
- [ ] **Step 4: Тесты** → `OK (74)`. **Commit**

```bash
git add www/css/home.css www/css/catalog.css
git commit -m "design(hex): сырые hex в home.css/catalog.css → токены светлой темы"
```

---

## Task 8: Бронзовый логотип + иконки Phosphor

**Files:**
- Modify: `www/images/logo_zip_optom.svg` (замена содержимым бронзового ассета)
- Modify: разметка с `<i class="fas …">` на редизайненных публичных поверхностях (`index.php`, карточки) — при быстрой замене на Phosphor

- [ ] **Step 1: Заменить логотип** — скопировать содержимое `docs/superpowers/specs/assets/2026-07-03-logo_zip_optom_bronze.svg` в `www/images/logo_zip_optom.svg` (форма 1:1, цвета A3: плашка `#1A1712`, подложка/язычок `#C6A24E`, буквы `#FBFAF6`).
- [ ] **Step 2: Проверка логотипа** — рендер: логотип бронзовый в шапке; если футер тёмный — логотип читается (тёмная плашка на ink-футере может слиться → при необходимости обернуть логотип светлой врезкой или использовать вариант плашки `#2A2620`; принять решение по факту рендера).
- [ ] **Step 3: Иконки (лёгкий проход)** — на редизайненных публичных поверхностях (hero, карточки, полосы доверия) FontAwesome (`fas fa-*`) заменить на эквиваленты Phosphor (`ph ph-*`), где просто. Калькулятор/глубоко-вшитые — НЕ трогать (отдельная чистка). `rg -c "fa-" www/index.php` — зафиксировать, что осталось (для отчёта), не гнаться за нулём.
- [ ] **Step 4: Проверка** — иконки на главной единой системой (Phosphor), без «двойных стилей» в одном блоке.
- [ ] **Step 5: Тесты** → `OK (74)`. **Commit**

```bash
git add www/images/logo_zip_optom.svg www/index.php
git commit -m "design(brand): бронзовый логотип + Phosphor-иконки на витрине"
```

---

## Task 9: Пер-страничная регрессия, контраст, скриншоты

**Files:** — (проверки; при находках — точечные фиксы в затронутых CSS/шаблонах)

- [ ] **Step 1: PHPUnit** — `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → `OK (74)`.
- [ ] **Step 2: `php -l`** по всем изменённым PHP (`index.php header.php footer.php`) — чисто.
- [ ] **Step 3: Все публичные страницы — светлые и когерентные** (dev-сервер): `/`, `/katalog_zip_paketov/`, `/product/1`, `/cart.php`, `/checkout.php`, `/review_add.php`, `/price.php`, `/kontakty.php`, `/oferta.php`, `/dostavka-i-oplata.php`, `/zip_paket_s_logotipom`, `/404`. Каждая: HTTP 200 + визуально светлая, без тёмных «дыр»/нечитаемого текста. Открыть калькулятор, cookie-баннер, модалку быстрого заказа, FAB — проверить светлую консистентность.
- [ ] **Step 4: Контраст** — текст-ссылки/мелкий текст на `--z-mint-2` (не на светлой бронзе); основной текст `--z-text` на бумаге; CTA-текст тёмный на бронзе. Пробежать ключевые экраны глазами на читаемость.
- [ ] **Step 5: Остаточная «тёмная»** — `rg -n "#070E18|#0A1422|#16181C|#EAF2FB|#A9C0DA|#5FE3D0|#FFB020" www/css www/*.php` → пусто или только осознанные ink-панели/исключения (задокументировать список в отчёте).
- [ ] **Step 6: Скриншоты** — снять desktop+mobile главной и каталога (`zshots\shoot.py` при наличии, иначе браузером) для «до/после» в PR.
- [ ] **Step 7: Финальный commit**

```bash
git add -A
git commit -m "design: регрессионный прогон светлой темы A3 (все публичные страницы)"
```

---

## Что НЕ входит (следующее)

- Рескин **админки** (`admin/*`) под общий стиль — отдельный проход.
- **WebP/AVIF** и реальные фото товаров/галерея — asset-задача.
- Полная физическая зачистка легаси `style.css` (здесь только нейтрализация цветов + безопасный трим).
- Полный перевод **всех** иконок на Phosphor (калькулятор и глубоко-вшитые — отдельно).
- Тёмная тема как A/B-вариант (если понадобится — вернуть через альт-набор токенов; имена сохранены, так что технически возможно).
