# Витрина — Фаза B (Доверие: отзывы, кейсы, FAQ, гарантии, мессенджеры) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Закрыть блок «доверие» продающей витрины: настоящие отзывы из БД (с модерацией и админкой) вместо заглушек, кураторские кейсы/логотипы клиентов, FAQ по опту с FAQPage-микроразметкой, расширенный блок гарантий/документов и плавающий виджет мессенджеров/обратного звонка.

**Architecture:** Отзывы — новая таблица `reviews` + модуль `includes/reviews.php` (чистые функции покрыты PHPUnit; доступ к БД — тонкие функции). Публичная форма отзыва пишет `is_approved=0` через существующий лид-паттерн (reCAPTCHA + 152-ФЗ согласие). Админ-модерация — отдельная страница по паттерну `admin/orders.php`. Кейсы/логотипы и FAQ — редактируемые data-файлы (`data/*.php`), рендер на главной; FAQ отдаёт FAQPage JSON-LD. Всё строится на тёмной дизайн-системе `--z-*` без «сырых» цветов, чтобы будущий переход на светлую тему был ремапом токенов.

**Tech Stack:** PHP 8.3, MySQL 8.x (PDO), PHPUnit 9.6, ванильный JS. Тёмная тема — `css/site-premium.css` + `css/home.css`. Без новых зависимостей.

## Global Constraints

- PHP путь (не в PATH): `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`. Тесты из `www/`: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → ожидается `OK`.
- **ЕДИНЫЙ СТИЛЬ (гейт приёмки каждой UI-задачи):** только токены `--z-*` из `css/site-premium.css` и существующие классы-компоненты (`.z-section`, `.z-wrap`, `.z-card`, `.z-lift`, `.z-glass`, `.z-btn*`, `.z-sec-head`, `.z-eyebrow`, `.z-adv-grid`, `.z-ico`, `data-reveal`). **НОЛЬ новых «сырых» hex-цветов** — любой цвет только через `var(--z-*)`. Цель: будущий переход тёмная→светлая = ремап значений токенов, а не переписывание. Проверка: `rg -n "#[0-9a-fA-F]{3,8}" <изменённый-файл>` в новых строках — пусто (кроме уже существующего кода).
- Тесты — на чистых функциях; БД в тестах НЕ трогаем (массивы в памяти). Текущая база — 67 тестов.
- Данные отзывов — только из БД (`reviews`, `is_approved=1`). Никаких захардкоженных массивов отзывов в разметке (заменяем существующую заглушку).
- 152-ФЗ: публичная форма отзыва обязана иметь чекбокс согласия на обработку ПДн со ссылкой `/polconf.html` (как в checkout/quick-order/price).
- Спам-защита формы отзыва: reCAPTCHA v3 (site key `6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf`, серверная `recaptcha_verify()` из `includes/recaptcha.php`).
- `www/includes/config.php` — GITIGNORED. Любые новые константы добавлять в трекаемый `includes/config.example.php` + давать безопасный дефолт в коде.
- Админ-страницы: паттерн `require_once __DIR__ . '/../includes/init.php'; require includes/security_config.php; auth.php; permissions.php; checkAdminAuth();` + проверка `$_SESSION['admin_id']` + `verifyCsrfToken($_POST['csrf_token'])` на POST (см. `admin/orders.php:1-30`).
- Тексты — русский, деловой опт. Мессенджеры проекта: Telegram `@zlock_sales_bot`, телефон `SUPPORT_PHONE`.
- Антипаттерны запрещены: фейковые отзывы/счётчики, агрессивные поп-апы, накрутка рейтинга.
- Миграции: `db/migrations/ГГГГ-ММ-ДД-name.sql` (см. существующие).

---

## Task 1: Таблица `reviews` (миграция)

**Files:**
- Create: `db/migrations/2026-07-02-reviews.sql`

- [ ] **Step 1: Написать миграцию**

```sql
-- Отзывы покупателей (с модерацией). Публичные показываем при is_approved=1.
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `author_name` VARCHAR(100) NOT NULL,
  `author_role` VARCHAR(120) DEFAULT NULL,        -- «оптовый покупатель», компания/сфера
  `rating` TINYINT NOT NULL DEFAULT 5,            -- 1..5
  `body` TEXT NOT NULL,
  `product_id` INT DEFAULT NULL,                  -- NULL = общий отзыв о компании
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0,    -- модерация
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_approved` (`is_approved`, `created_at`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Стартовое наполнение (одобренные) — заменяет захардкоженные заглушки главной.
INSERT INTO `reviews` (`author_name`,`author_role`,`rating`,`body`,`is_approved`) VALUES
('Алексей М.','оптовый покупатель',5,'Заказывали партию слайдеров под фасовку — приехало в срок, качество отличное. Менеджер пересчитал цену под наш объём.',1),
('ООО «Пример»','производство продуктов',5,'Берём грипперы регулярно. Удобно, что можно по счёту для юрлица. Цена на объём приятная.',1),
('Ирина К.','маркетплейс-селлер',5,'Нужна была упаковка с печатью — сделали образец бесплатно, потом тираж. Рекомендую.',1);
```

- [ ] **Step 2: Применить на локальной БД и проверить**

Run:
```powershell
& "C:\laragon\bin\mysql\...\bin\mysql.exe" -u root zippaket < db/migrations/2026-07-02-reviews.sql
```
(или через тот же клиент, что и прошлые миграции). Проверка:
```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -r "chdir('www'); require 'includes/config.php'; var_dump(getDbConnection()->query('SELECT COUNT(*) FROM reviews WHERE is_approved=1')->fetchColumn());"
```
Expected: `string(1) "3"` (или int 3), без ошибок.

- [ ] **Step 3: Commit**

```bash
git add db/migrations/2026-07-02-reviews.sql
git commit -m "feat(db): таблица reviews (отзывы с модерацией) + стартовые записи"
```

---

## Task 2: Модуль `includes/reviews.php` — чистые функции + доступ к БД

Чистая логика (звёзды, валидация, clamp) — тестируется; доступ к БД — тонкие функции (не тестируем, паттерн проекта).

**Files:**
- Create: `www/includes/reviews.php`
- Test: `www/tests/ReviewsTest.php`

**Interfaces:**
- Produces (чистые, тестируемые):
  - `review_clamp_rating(int $r): int` — зажимает в 1..5.
  - `review_stars(int $r): string` — строка «★★★★★»/«★★★★☆» длиной 5 (заполненные = clamp).
  - `review_validate(array $in): array` — `['ok'=>bool, 'data'=>[...], 'errors'=>[...]]`; требует `author_name` (2..100) и `body` (10..2000), rating→clamp, author_role необязателен.
- Produces (доступ к БД, без тестов):
  - `reviews_approved(int $limit = 6, ?int $productId = null): array`
  - `review_add(array $data): bool` — вставляет с `is_approved=0`.
  - `reviews_all(): array`, `review_set_approved(int $id, bool $ok): void`, `review_delete(int $id): void` (для админки).

- [ ] **Step 1: Написать падающий тест**

```php
<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/reviews.php';

class ReviewsTest extends TestCase
{
    public function testClampRating(): void
    {
        $this->assertSame(1, review_clamp_rating(0));
        $this->assertSame(5, review_clamp_rating(9));
        $this->assertSame(4, review_clamp_rating(4));
    }

    public function testStars(): void
    {
        $this->assertSame('★★★★★', review_stars(5));
        $this->assertSame('★★★★☆', review_stars(4));
        $this->assertSame('★☆☆☆☆', review_stars(0)); // clamp → 1
    }

    public function testValidateOk(): void
    {
        $r = review_validate(['author_name' => 'Иван', 'body' => 'Отличная упаковка, брали оптом.', 'rating' => '7']);
        $this->assertTrue($r['ok']);
        $this->assertSame(5, $r['data']['rating']);        // clamp
        $this->assertSame('Иван', $r['data']['author_name']);
    }

    public function testValidateRejectsShort(): void
    {
        $r = review_validate(['author_name' => '', 'body' => 'мало']);
        $this->assertFalse($r['ok']);
        $this->assertArrayHasKey('author_name', $r['errors']);
        $this->assertArrayHasKey('body', $r['errors']);
    }
}
```

- [ ] **Step 2: Запустить — убедиться, что падает**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/ReviewsTest.php`
Expected: FAIL — `Failed opening required '.../includes/reviews.php'`.

- [ ] **Step 3: Реализовать `includes/reviews.php`**

```php
<?php
require_once __DIR__ . '/config.php';

/** Зажать рейтинг в 1..5. */
function review_clamp_rating(int $r): int {
    return max(1, min(5, $r));
}

/** Строка звёзд длиной 5: заполненные ★, остальные ☆. */
function review_stars(int $r): string {
    $n = review_clamp_rating($r);
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}

/** Чистая валидация отзыва. Возвращает ok/data/errors, БД не трогает. */
function review_validate(array $in): array {
    $errors = [];
    $name = trim((string)($in['author_name'] ?? ''));
    $body = trim((string)($in['body'] ?? ''));
    $role = trim((string)($in['author_role'] ?? ''));
    $rating = review_clamp_rating((int)($in['rating'] ?? 5));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['author_name'] = 'Укажите имя (2–100 символов)';
    }
    if (mb_strlen($body) < 10 || mb_strlen($body) > 2000) {
        $errors['body'] = 'Текст отзыва — от 10 до 2000 символов';
    }
    return [
        'ok' => empty($errors),
        'data' => ['author_name' => $name, 'author_role' => $role !== '' ? $role : null, 'body' => $body, 'rating' => $rating],
        'errors' => $errors,
    ];
}

/** Одобренные отзывы (общие или по товару), новые сверху. */
function reviews_approved(int $limit = 6, ?int $productId = null): array {
    try {
        $db = getDbConnection();
        if ($productId !== null) {
            $stmt = $db->prepare("SELECT * FROM reviews WHERE is_approved=1 AND product_id=? ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit);
            $stmt->execute([$productId]);
        } else {
            $stmt = $db->query("SELECT * FROM reviews WHERE is_approved=1 ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit);
        }
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) { error_log('reviews_approved: ' . $e->getMessage()); return []; }
}

/** Добавить отзыв (на модерацию, is_approved=0). $data — из review_validate()['data']. */
function review_add(array $data): bool {
    try {
        $stmt = getDbConnection()->prepare(
            "INSERT INTO reviews (author_name, author_role, rating, body, product_id, is_approved)
             VALUES (:name, :role, :rating, :body, :pid, 0)"
        );
        return $stmt->execute([
            ':name' => $data['author_name'], ':role' => $data['author_role'] ?? null,
            ':rating' => review_clamp_rating((int)($data['rating'] ?? 5)),
            ':body' => $data['body'], ':pid' => $data['product_id'] ?? null,
        ]);
    } catch (PDOException $e) { error_log('review_add: ' . $e->getMessage()); return false; }
}

/** Все отзывы для админки (новые сверху). */
function reviews_all(): array {
    try { return getDbConnection()->query("SELECT * FROM reviews ORDER BY is_approved ASC, created_at DESC, id DESC")->fetchAll() ?: []; }
    catch (PDOException $e) { error_log('reviews_all: ' . $e->getMessage()); return []; }
}

function review_set_approved(int $id, bool $ok): void {
    try { $s = getDbConnection()->prepare("UPDATE reviews SET is_approved=? WHERE id=?"); $s->execute([$ok ? 1 : 0, $id]); }
    catch (PDOException $e) { error_log('review_set_approved: ' . $e->getMessage()); }
}

function review_delete(int $id): void {
    try { $s = getDbConnection()->prepare("DELETE FROM reviews WHERE id=?"); $s->execute([$id]); }
    catch (PDOException $e) { error_log('review_delete: ' . $e->getMessage()); }
}
```

- [ ] **Step 4: Запустить — убедиться, что проходит**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/ReviewsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Прогнать весь набор**

Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests`
Expected: `OK` (67 + 4 = 71 tests).

- [ ] **Step 6: Commit**

```bash
git add www/includes/reviews.php www/tests/ReviewsTest.php
git commit -m "feat(reviews): модуль reviews.php (звёзды/валидация/доступ) + тесты"
```

---

## Task 3: Отзывы на главной из БД (замена заглушек)

**Files:**
- Modify: `www/index.php` (секция `466-503`: заменить массив-заглушку на данные из БД; блок гарантий не трогаем в этой задаче — расширяется в Task 8)

**Interfaces:**
- Consumes: `reviews_approved(6)`, `review_stars()`.

- [ ] **Step 1: Подключить модуль** — в начале `index.php` (рядом с прочими `require_once`): `require_once __DIR__ . '/includes/reviews.php';`

- [ ] **Step 2: Заменить заглушку** — блок `index.php:474-493` (php-массив `$reviews` + foreach) на:

```php
                        <?php
                        $reviews = reviews_approved(6);
                        foreach ($reviews as $r): ?>
                        <div class="z-card z-lift" data-reveal>
                            <div style="color:var(--z-gold);margin-bottom:10px;font-size:14px"><?= review_stars((int)$r['rating']) ?></div>
                            <p style="margin:0 0 16px"><?= htmlspecialchars($r['body']) ?></p>
                            <div style="display:flex;align-items:center;gap:12px">
                                <span class="z-ico" style="width:42px;height:42px;font-size:18px;margin:0"><i class="ph ph-user"></i></span>
                                <div>
                                    <div style="font-weight:700;color:var(--z-text)"><?= htmlspecialchars($r['author_name']) ?></div>
                                    <div style="font-size:13px;color:var(--z-text-3)"><?= htmlspecialchars((string)($r['author_role'] ?? '')) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
```
(Замечание по стилю: `color:#fff` в старой заглушке заменён на `var(--z-text)` — гейт единого стиля.) Убрать комментарий-предупреждение «⚠️ ЗАГЛУШКИ». Секцию не рендерить, если отзывов нет: обернуть `<?php if ($reviews): ?> … <?php endif; ?>` вокруг `.z-adv-grid` (блок гарантий оставить видимым всегда).

- [ ] **Step 3: Проверка**

Run: `php -l index.php` → clean. Поднять сервер, открыть `/`. Отзывы отображаются из БД (3 стартовых). Стиль-гейт:
Run: `rg -n "#[0-9a-fA-F]{3,6}" www/index.php | grep -n "466\|4[7-9][0-9]"` — в новых строках секции сырых hex нет (звёзды/текст через `var(--z-*)`).

- [ ] **Step 4: Commit**

```bash
git add www/index.php
git commit -m "feat(home): отзывы на главной из БД (замена заглушек), стиль на токенах"
```

---

## Task 4: Публичная форма отзыва (модерация + 152-ФЗ + reCAPTCHA)

Страница/секция, где покупатель оставляет отзыв. Пишем `is_approved=0`.

**Files:**
- Create: `www/review_add.php` (GET — форма в тёмной теме; POST — валидация + reCAPTCHA + согласие → `review_add()`)

**Interfaces:**
- Consumes: `review_validate()`, `review_add()`, `recaptcha_verify()`, `generateCsrfToken()`/`verifyCsrfToken()`.

- [ ] **Step 1: Реализовать `www/review_add.php`**

Скелет (по образцу `checkout.php`/`price.php`): подключить `includes/init.php` (сессия/csrf), `includes/reviews.php`, `includes/recaptcha.php`. На POST:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $errors['_'] = 'Сессия истекла, обновите страницу'; }
    elseif (!recaptcha_verify($_POST['recaptcha_token'] ?? '', 'review')) { $errors['_'] = 'Проверка безопасности не пройдена'; }
    elseif (empty($_POST['pdn_consent'])) { $errors['_'] = 'Подтвердите согласие на обработку персональных данных'; }
    else {
        $v = review_validate($_POST);
        if (!$v['ok']) { $errors = $v['errors']; }
        else { $ok = review_add($v['data']); $sent = $ok; }
    }
}
```
Форма (тёмная тема, классы `.z-form`/`.z-consent`/`.z-btn` — как в price.php/checkout): имя, роль/компания, рейтинг (select 1–5), текст, обязательный чекбокс согласия ПДн со ссылкой `/polconf.html`, скрытый `recaptcha_token`, reCAPTCHA v3 script + `grecaptcha.execute(..., {action:'review'})` перед сабмитом (как в checkout.php). После успеха — сообщение «Спасибо! Отзыв появится после модерации». Подключить `header.php`/`footer.php`.

- [ ] **Step 2: Проверка**

`php -l review_add.php` clean. Поднять сервер, GET `/review_add.php` → форма отдаётся (`HTTP 200`). Отправить тестовый отзыв → в БД строка `is_approved=0`:
```powershell
& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -r "chdir('www'); require 'includes/config.php'; var_dump(getDbConnection()->query('SELECT id,is_approved FROM reviews ORDER BY id DESC LIMIT 1')->fetch());"
```
Expected: последняя запись `is_approved=0`. Стиль-гейт: `rg -n "#[0-9a-fA-F]{3,6}" www/review_add.php` — только через токены (либо пусто).

- [ ] **Step 3: Commit**

```bash
git add www/review_add.php
git commit -m "feat(reviews): публичная форма отзыва (модерация, 152-ФЗ, reCAPTCHA)"
```

---

## Task 5: Админ-модерация отзывов

**Files:**
- Create: `www/admin/reviews.php` (список + approve/reject/delete)

**Interfaces:**
- Consumes: `reviews_all()`, `review_set_approved()`, `review_delete()`, `checkAdminAuth()`, `verifyCsrfToken()`.

- [ ] **Step 1: Реализовать `admin/reviews.php`** по образцу `admin/orders.php`

Шапка авторизации (verbatim паттерн `orders.php:6-17`): `init.php` + `security_config.php` + `auth.php` + `permissions.php` + `checkAdminAuth()` + проверка `$_SESSION['admin_id']`. На POST (`verifyCsrfToken`):
```php
$id = (int)($_POST['id'] ?? 0);
switch ($_POST['action'] ?? '') {
    case 'approve': review_set_approved($id, true);  $notice = 'Отзыв опубликован'; break;
    case 'reject':  review_set_approved($id, false); $notice = 'Отзыв скрыт'; break;
    case 'delete':  review_delete($id);              $notice = 'Отзыв удалён'; break;
}
```
Таблица `reviews_all()`: колонки — id, дата, имя/роль, рейтинг (`review_stars`), текст, статус (одобрен/на модерации), кнопки-формы (approve/reject/delete) с `csrf_token` и `id`. Вёрстка — как в существующей админке (светлая тема админки — это ОК, у неё свой концепт; единый стиль касается публичной витрины). Добавить пункт в навигацию админки, если есть общий admin-nav (проверить `admin/index.php`).

- [ ] **Step 2: Проверка**

`php -l admin/reviews.php` clean. Залогиниться в админку, открыть `/admin/reviews.php` → список отзывов, одобрение/скрытие/удаление меняют `is_approved` (проверить в БД). Открыть `/` — одобренные видны, скрытые нет.

- [ ] **Step 3: Commit**

```bash
git add www/admin/reviews.php
git commit -m "feat(admin): модерация отзывов (approve/reject/delete)"
```

---

## Task 6: Кейсы отгрузок + логотипы клиентов (кураторский блок)

Редактируемый контент-блок (владелец даёт тексты/лого). Без CRUD — data-файл.

**Files:**
- Create: `www/data/testimonials.php` (возвращает массив кейсов + путей к лого)
- Modify: `www/index.php` (секция после отзывов)

**Interfaces:**
- Produces: `www/data/testimonials.php` → `return ['cases'=>[['company'=>...,'result'=>...,'detail'=>...]], 'logos'=>['/images/clients/....png', ...]];`

- [ ] **Step 1: Создать `data/testimonials.php`** с 2–3 примерами кейсов и (опционально) массивом путей к лого (пустой массив, если лого пока нет — блок лого рендерится только если непусто). Комментарий: «владелец заполняет реальными данными».

- [ ] **Step 2: Секция на главной** (после секции отзывов, до контактов):
```php
<?php $tm = require __DIR__ . '/data/testimonials.php'; if (!empty($tm['cases'])): ?>
<section class="z-section z-cases" data-reveal>
    <div class="z-wrap">
        <div class="z-sec-head z-center"><div class="z-eyebrow">Кейсы</div><h2 class="z-h2">Отгрузки клиентам</h2></div>
        <div class="z-adv-grid">
            <?php foreach ($tm['cases'] as $c): ?>
            <div class="z-card z-lift">
                <div style="font-weight:800;color:var(--z-mint);font-size:22px"><?= htmlspecialchars($c['result']) ?></div>
                <div style="font-weight:700;color:var(--z-text);margin:6px 0"><?= htmlspecialchars($c['company']) ?></div>
                <p style="margin:0;color:var(--z-text-2)"><?= htmlspecialchars($c['detail']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($tm['logos'])): ?>
        <div class="z-glass" style="margin-top:24px;padding:20px;display:flex;gap:28px;flex-wrap:wrap;justify-content:center;align-items:center" data-reveal>
            <?php foreach ($tm['logos'] as $logo): ?><img src="<?= htmlspecialchars($logo) ?>" alt="Клиент" loading="lazy" style="height:38px;opacity:.8"><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
```

- [ ] **Step 3: Проверка** — `php -l index.php` clean; сервер: секция «Кейсы» видна с примерами; блок лого не рендерится при пустом массиве. Стиль-гейт: только `var(--z-*)`.

- [ ] **Step 4: Commit**

```bash
git add www/data/testimonials.php www/index.php
git commit -m "feat(home): блок кейсов отгрузок + логотипы клиентов (кураторский data-файл)"
```

---

## Task 7: FAQ по опту + FAQPage schema

**Files:**
- Create: `www/data/faq.php` (массив вопрос/ответ)
- Create: `www/includes/faq.php` (`faq_jsonld(array $items): string` — чистая, тестируется)
- Test: `www/tests/FaqTest.php`
- Modify: `www/index.php` (секция-аккордеон FAQ + вывод JSON-LD в `<head>`/перед секцией)

**Interfaces:**
- Produces: `faq_jsonld(array $items): string` — валидный `<script type="application/ld+json">` с `@type:FAQPage` и `mainEntity` из пар q/a.

- [ ] **Step 1: Падающий тест**

```php
<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/faq.php';

class FaqTest extends TestCase
{
    public function testJsonldStructure(): void
    {
        $out = faq_jsonld([['q' => 'Мин. партия?', 'a' => 'От 1000 шт.']]);
        $this->assertStringContainsString('application/ld+json', $out);
        $json = json_decode(preg_replace('#</?script[^>]*>#', '', $out), true);
        $this->assertSame('FAQPage', $json['@type']);
        $this->assertSame('Question', $json['mainEntity'][0]['@type']);
        $this->assertSame('Мин. партия?', $json['mainEntity'][0]['name']);
        $this->assertSame('От 1000 шт.', $json['mainEntity'][0]['acceptedAnswer']['text']);
    }
}
```

- [ ] **Step 2: Запустить — FAIL** (`Failed opening required '.../includes/faq.php'`).
Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests/FaqTest.php`

- [ ] **Step 3: Реализовать `includes/faq.php`**

```php
<?php
/** FAQPage JSON-LD из пар ['q'=>..., 'a'=>...]. Чистая, без вывода в буфер. */
function faq_jsonld(array $items): string {
    $entities = [];
    foreach ($items as $it) {
        $entities[] = [
            '@type' => 'Question',
            'name' => (string)($it['q'] ?? ''),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string)($it['a'] ?? '')],
        ];
    }
    $data = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities];
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}
```

- [ ] **Step 4: Запустить — PASS.** Затем весь набор:
Run: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests`
Expected: `OK` (71 + 1 = 72 tests).

- [ ] **Step 5: Данные `data/faq.php`** — 5–7 вопросов опта (мин. партия, скидки от объёма, доставка/сроки, оплата/НДС/документы, образцы, печать логотипа = только слайдеры). `return [['q'=>...,'a'=>...], ...];`

- [ ] **Step 6: Секция + schema в `index.php`** (перед контактами/FAQ, до `</main>`):
```php
<?php $faq = require __DIR__ . '/data/faq.php'; ?>
<?= faq_jsonld($faq) ?>
<section class="z-section z-faq" data-reveal>
    <div class="z-wrap">
        <div class="z-sec-head z-center"><div class="z-eyebrow">Вопросы</div><h2 class="z-h2">Частые вопросы опта</h2></div>
        <div class="z-faq-list">
            <?php foreach ($faq as $f): ?>
            <details class="z-card" style="margin-bottom:10px">
                <summary style="cursor:pointer;font-weight:700;color:var(--z-text)"><?= htmlspecialchars($f['q']) ?></summary>
                <p style="margin:10px 0 0;color:var(--z-text-2)"><?= htmlspecialchars($f['a']) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
```
(`require_once __DIR__ . '/includes/faq.php';` в начале index.php.)

- [ ] **Step 7: Проверка** — `php -l index.php` clean; suite 72 OK; сервер: FAQ-аккордеон работает (нативный `<details>`), в исходнике страницы есть один блок `application/ld+json` с `FAQPage`. Стиль-гейт на новых строках.

- [ ] **Step 8: Commit**

```bash
git add www/includes/faq.php www/tests/FaqTest.php www/data/faq.php www/index.php
git commit -m "feat(home): FAQ по опту + FAQPage schema (аккордеон на details)"
```

---

## Task 8: Расширенный блок гарантий/документов

Развить существующую полосу гарантий (`index.php:496-501`) до явного блока «работаем с юрлицами: НДС, закрывающие документы, договор, ГОСТ/сертификаты, возврат по закону».

**Files:**
- Modify: `www/index.php` (блок гарантий в секции отзывов/доверия)

- [ ] **Step 1: Расширить блок** — к существующим 4 пунктам добавить: «Договор и счёт для юрлиц», «Работаем с НДС», «Сертификаты на материалы EVA/ПВД». Использовать те же токены (`var(--z-text-2)`, `var(--z-mint)`) и иконки Phosphor (`ph-*`), что уже в блоке. Не вводить новых цветов.

- [ ] **Step 2: Проверка** — `php -l index.php`; сервер: блок показывает расширенный список; стиль-гейт (только токены). `rg -n "НДС|Договор|Сертификаты" www/index.php` → присутствуют.

- [ ] **Step 3: Commit**

```bash
git add www/index.php
git commit -m "feat(home): расширенный блок гарантий/документов (НДС, договор, сертификаты)"
```

---

## Task 9: Плавающий виджет мессенджеров/обратного звонка

Ненавязчивый FAB (не поп-ап): кнопка внизу справа → раскрывает Telegram/звонок. Без агрессии (антипаттерн запрещён).

**Files:**
- Create: `www/includes/contact_fab.php` (разметка виджета)
- Create: `www/css/fab.css` (стили на токенах) — или дописать в `home.css`
- Modify: `www/footer.php` (подключить виджет один раз на всех страницах)

- [ ] **Step 1: `includes/contact_fab.php`** — кнопка `.z-fab` (position:fixed; bottom/right), по клику toggle списка ссылок: Telegram (`https://t.me/zlock_sales_bot`), тел (`tel:` из `SUPPORT_PHONE`), «Заказать звонок» (ссылка на `#leadForm`/callback). Иконки Phosphor. Только токены `--z-*` (фон `--z-bg-2`/`--z-surface`, акцент `--z-mint`/`--z-tg`). Никакого авто-раскрытия/таймера.

- [ ] **Step 2: Стили** — `.z-fab`, `.z-fab-list`, `.z-fab-item` в `css/home.css` (или новый `fab.css`, подключить в `header.php`). Мелкий JS-toggle можно инлайн в `contact_fab.php` (делегирование клика).

- [ ] **Step 3: Подключить в `footer.php`** — `include __DIR__ . '/includes/contact_fab.php';` перед закрытием (один раз, на всех страницах, где включён footer).

- [ ] **Step 4: Проверка** — `php -l footer.php includes/contact_fab.php`; сервер: FAB виден на `/`, каталоге, товаре; клик раскрывает Telegram/тел/звонок; тел берётся из `SUPPORT_PHONE`. Не перекрывает cookie-баннер/контент. Стиль-гейт: только токены.

- [ ] **Step 5: Commit**

```bash
git add www/includes/contact_fab.php www/footer.php www/css/home.css
git commit -m "feat(ux): плавающий виджет мессенджеров/звонка (Telegram/тел, на токенах)"
```

---

## Task 10: Регрессия и финальный прогон Фазы B

**Files:** — (проверки; при находках — точечные фиксы)

- [ ] **Step 1: Все тесты** — Run из `www/`: `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php tests` → `OK` (72 tests).
- [ ] **Step 2: `php -l`** по всем изменённым PHP (`index.php review_add.php footer.php admin/reviews.php includes/reviews.php includes/faq.php includes/contact_fab.php`) — чисто.
- [ ] **Step 3: Ключевые страницы 200** (сервер): `/`, `/review_add.php`, `/admin/reviews.php` (после логина), `/katalog_zip_paketov/`, `/product/1`.
- [ ] **Step 4: Флоу доверия** — отзыв через форму → `is_approved=0` → одобрение в админке → виден на `/`. FAQ отдаёт FAQPage JSON-LD (один блок). Кейсы/гарантии/FAB видны.
- [ ] **Step 5: СТИЛЬ-ГЕЙТ (тщательно):** во всех изменённых публичных файлах новые цвета — только `var(--z-*)`. Run: `rg -n "#[0-9a-fA-F]{3,8}" www/index.php www/review_add.php www/includes/contact_fab.php` — новые строки Фазы B без сырых hex (существующий легаси не в счёт). Любой сырой цвет → заменить на токен.
- [ ] **Step 6: Финальный commit** — `git commit --allow-empty -m "test(trust): регрессионный прогон Фазы B"`.

---

## Что НЕ входит в Фазу B (следующие планы)

- **Фаза C (SEO/перф):** canonical, мета+OG на статике, Product JSON-LD (stock/rating — теперь можно подтянуть `AggregateRating` из `reviews`), схема в листинге, WebP/srcset для hero, font-display swap, минификация CSS, LocalBusiness, фикс robots.txt.
- **Единая дизайн-система (после B/C, решение владельца): переход тёмная→СВЕТЛАЯ** через ремап токенов `--z-*` + вывод из игры легаси (`style.css` 78 КБ, `--pm-*`, ~390 сырых hex), единая система иконок. Фаза B специально строится только на токенах, чтобы этот переход был дешёвым.
- **Фаза D:** брошенная корзина-догон, сравнение, подписка на прайс, личный кабинет.
- Реальные фото товаров и галерея — при поступлении снимков.
- Связка `reviews.rating` → агрегированный рейтинг товара/`AggregateRating` schema — в Фазе C (SEO).
