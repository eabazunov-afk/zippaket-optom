<?php
// Подключаем конфигурацию
require_once 'includes/config.php';
require_once 'includes/catalog_functions.php';

// UTM трекер инициализируется в header.php
if (file_exists(__DIR__ . '/includes/utm_tracker.php')) {
    require_once __DIR__ . '/includes/utm_tracker.php';
}

/* ---- Данные товаров для карточек главной (из БД, не из захардкоженного массива) ---- */
$zSliders = [];
$zGrippers = [];
try {
    $zdb = getDbConnection();
    $zrows = $zdb->query(
        "SELECT id, category, short_name, full_name, width, height, thickness, color,
                price_rub, stock_quantity, min_order_qty, qty_step
         FROM products
         WHERE is_active = 1 AND price_rub > 0
         ORDER BY stock_quantity DESC"
    )->fetchAll();
    foreach ($zrows as $r) {
        if (mb_stripos((string)$r['category'], 'слайдер') !== false) {
            $zSliders[] = $r;
        } else {
            $zGrippers[] = $r;
        }
    }
} catch (Throwable $e) {
    error_log('home products load: ' . $e->getMessage());
}

// Берём по 3 самых ходовых из каждой группы для секции распродажи
$zSlidersTop = array_slice($zSliders, 0, 3);
$zGrippersTop = array_slice($zGrippers, 0, 3);

// Эталон прогресс-бара — максимум ВНУТРИ группы (списки отсортированы по убыванию остатка).
// Сравнивать слайдеры с грипперами нельзя: у грипперов остатки на порядок больше.
$zSliderMax = $zSlidersTop ? (int)$zSlidersTop[0]['stock_quantity'] : 1;
$zGripperMax = $zGrippersTop ? (int)$zGrippersTop[0]['stock_quantity'] : 1;
// Порог «мало» — по абсолютному остатку (а не относительному), чтобы бестселлеры не помечались ложно.
define('Z_LOW_STOCK', 100000);

/** Ценовые уровни из price_rub: розница, опт 20к (−8%), опт 300к (−18%). */
function z_price(float $base, float $mult): string {
    return number_format($base * $mult, 2, ',', ' ');
}
/** Размер из мм в «25 × 30 см». */
function z_size(?int $w, ?int $h): string {
    $f = function ($mm) { $cm = $mm / 10; return rtrim(rtrim(number_format($cm, 1, '.', ''), '0'), '.'); };
    if (!$w || !$h) return '';
    return $f($w) . ' × ' . $f($h) . ' см';
}
/** Фото слайдера по цвету: матовый → eva, прозрачный → pvd. */
function z_slider_img(array $r): string {
    return (mb_stripos((string)$r['color'], 'мат') !== false) ? '/images/eva.png' : '/images/pvd.png';
}

/** Карточка товара главной. $withPhoto — слайдеры (фото), иначе грипперы (заглушка). */
function z_card(array $r, bool $withPhoto, int $maxStock): string {
    $base = (float)$r['price_rub'];
    $stock = (int)$r['stock_quantity'];
    $fill = max(10, min(100, (int)round($stock / max(1, $maxStock) * 100)));
    $low = $stock < Z_LOW_STOCK;
    $size = z_size($r['width'] !== null ? (int)$r['width'] : null, $r['height'] !== null ? (int)$r['height'] : null);
    $mk = $r['thickness'] ? ((int)$r['thickness'] . ' мкм') : 'стандарт';
    $name = htmlspecialchars($r['short_name'] ?: $r['full_name']);

    if ($withPhoto) {
        $header = '<div class="z-prod-photo"><img src="' . htmlspecialchars(z_slider_img($r)) . '" alt="ZIP-пакет ' . htmlspecialchars($size) . '" loading="lazy"></div>';
    } else {
        // Грипперы (zip-lock, press-seal) — реальное фото
        $header = '<div class="z-prod-photo"><img src="/images/gripper.jpg" alt="ZIP-LOCK пакет (гриппер) ' . htmlspecialchars($size) . '" loading="lazy"></div>';
    }

    $stockLabel = number_format($stock, 0, '', ' ');

    ob_start(); ?>
    <article class="z-prod z-lift" data-reveal>
        <?= $header ?>
        <div class="z-prod-top">
            <div>
                <div class="z-prod-size"><?= htmlspecialchars($size ?: $name) ?></div>
                <div class="z-prod-mk">толщина <?= htmlspecialchars($mk) ?></div>
            </div>
            <span class="z-prod-ico"><i class="ph ph-package"></i></span>
        </div>
        <div class="z-prices z-tnum">
            <div class="row"><span>Опт от 300к</span><span class="p-main"><?= z_price($base, 0.82) ?> ₽/шт</span></div>
            <div class="row"><span>Опт от 20к</span><span class="p-sec"><?= z_price($base, 0.92) ?> ₽/шт</span></div>
            <div class="row"><span>Розница от 3к</span><span class="p-sec"><?= z_price($base, 1.0) ?> ₽/шт</span></div>
        </div>
        <div class="z-stock<?= $low ? ' low' : '' ?>">
            <div class="lbl"><span><i class="ph ph-warehouse"></i>В наличии: <span class="z-tnum"><?= $stockLabel ?></span> шт</span><?= $low ? '<span class="low-tag">мало</span>' : '' ?></div>
            <div class="z-bar"><i data-bar="<?= $fill ?>"></i></div>
        </div>
        <button class="z-add js-cart-add"
                data-id="<?= (int)$r['id'] ?>"
                data-name="<?= htmlspecialchars($r['short_name'] ?: $r['full_name']) ?>"
                data-price="<?= htmlspecialchars((string)$base) ?>"
                data-min="<?= (int)($r['min_order_qty'] ?? 1) ?>"
                data-step="<?= (int)($r['qty_step'] ?? 1) ?>">
            <i class="ph ph-shopping-cart-simple"></i>В корзину
        </button>
    </article>
    <?php
    return ob_get_clean();
}

// Дата окончания акции (с сервера): ближайшая «пятница 23:59» + запас, стабильна в пределах суток
$zSaleEnd = (strtotime('today 23:59:59') + 3 * 86400) * 1000;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZIP-пакеты от производителя | Производство на заказ | Завод по производству зип пакетов</title>
    <meta name="description" content="Производство ZIP-пакетов на заказ. Собственное производство, печать любого тиража, доставка по РФ. Бесплатные образцы и расчёт стоимости онлайн.">

    <!-- Open Graph -->
    <meta property="og:title" content="ZIP-пакеты от производителя | Завод по производству зип пакетов">
    <meta property="og:description" content="Производство ZIP-пакетов на заказ. Собственное производство, быстрые сроки, гарантия качества.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zippaket-optom.ru/">
    <meta property="og:image" content="https://zippaket-optom.ru/images/og-image.jpg">
    <meta name="yandex-verification" content="300261c5f186d190">

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/zlock.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="images/zlock.ico">
    <link rel="icon" href="https://zippaket-optom.ru/images/favicon.ico" type="image/x-icon">

    <!-- reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf"></script>

    <!-- Icons (FontAwesome для калькулятора/cookie/модалки/футера) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Стили: legacy (шапка/футер/калькулятор/cookie/модал) + премиум главной -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">

    <!-- JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "ZLOCK - Производство zip пакетов оптом",
      "url": "https://zippaket-optom.ru/",
      "logo": "https://zippaket-optom.ru/images/logo.png",
      "description": "Производство ZIP-пакетов на заказ",
      "address": { "@type": "PostalAddress", "addressLocality": "Москва", "addressCountry": "RU" },
      "contactPoint": { "@type": "ContactPoint", "telephone": "+7 (920) 346-50-67", "contactType": "customer service", "availableLanguage": "Russian" }
    }
    </script>
</head>
<body class="zlock">
    <div class="site-wrapper z-page">
        <!-- Фоновые слои премиум-темы -->
        <div class="z-mesh"></div>
        <div class="z-veil"></div>
        <div class="z-grain"></div>

        <div class="z-content">
        <!-- Header -->
        <?php include 'header.php'; ?>

        <main class="main-content">

            <!-- ===== HERO ===== -->
            <section id="top" class="z-section">
                <div class="z-wrap z-hero">
                    <div>
                        <div class="z-badge" data-reveal><i class="ph ph-seal-check"></i><span>Производитель №1 в России</span></div>
                        <h1 class="z-h1" data-reveal>Производство <span class="z-grad">ZIP-пакетов</span><br>на заказ от 1 дня</h1>
                        <p class="z-hero-sub" data-reveal>Расчёт стоимости за 10 минут — напрямую от производителя со скидкой до 30%. Собственное производство, любой тираж, гарантия качества.</p>
                        <div class="z-cta-row" data-reveal>
                            <a href="#calculator" class="z-btn z-btn-gold z-shine"><i class="ph ph-calculator"></i>Рассчитать стоимость</a>
                            <a href="/katalog_zip_paketov/" class="z-btn z-btn-glass"><i class="ph ph-package"></i>Весь каталог</a>
                        </div>
                        <div class="z-checks" data-reveal>
                            <div class="z-check"><i class="ph ph-check-circle"></i>Бесплатные образцы</div>
                            <div class="z-check"><i class="ph ph-check-circle"></i>Доставка по РФ</div>
                            <div class="z-check"><i class="ph ph-check-circle"></i>Гарантия качества</div>
                        </div>
                    </div>
                    <div class="z-hero-visual" data-reveal>
                        <div class="z-glow"></div>
                        <div class="z-hero-card">
                            <img src="/images/pvd.png" alt="ZIP-пакет с бегунком">
                            <div class="z-card-veil"></div>
                            <div class="z-hero-cap">
                                <div class="z-chip-mint">ПВД · с бегунком</div>
                                <div style="font-weight:800;font-size:20px;color:#fff;line-height:1.2">Кристальная<br>прозрачность</div>
                            </div>
                        </div>
                        <div class="z-floatcard z-f1"><span class="ic" style="background:rgba(255,176,32,.16);color:#FFB020"><i class="ph ph-tag"></i></span><div><small>Цена</small><b>от 0,35 ₽/шт</b></div></div>
                        <div class="z-floatcard z-f2"><span class="ic" style="background:rgba(95,227,208,.16);color:#5FE3D0"><i class="ph ph-truck"></i></span><div><small>Доставка</small><b>1–3 дня по РФ</b></div></div>
                        <div class="z-floatcard z-f3"><span class="ic" style="background:rgba(95,227,208,.16);color:#5FE3D0"><i class="ph ph-gift"></i></span><div><small>Образец</small><b>бесплатно</b></div></div>
                    </div>
                </div>
            </section>

            <!-- ===== ЦИФРЫ ДОВЕРИЯ ===== -->
            <section class="z-section" style="padding-top:0">
                <div class="z-wrap">
                    <div class="z-glass z-stats" data-reveal>
                        <div class="z-stat"><div class="num z-tnum"><span data-count="7" data-suffix=" млн+">0</span></div><div class="lbl">пакетов в наличии</div></div>
                        <div class="z-stat"><div class="num mint z-tnum"><span data-count="30" data-prefix="до " data-suffix="%">0</span></div><div class="lbl">скидка от производителя</div></div>
                        <div class="z-stat"><div class="num z-tnum"><span data-count="10" data-suffix=" мин">0</span></div><div class="lbl">расчёт стоимости</div></div>
                        <div class="z-stat"><div class="num z-tnum"><span data-count="1" data-suffix=" дня">0</span></div><div class="lbl">срок производства от</div></div>
                    </div>
                </div>
            </section>

            <!-- ===== ПРЕИМУЩЕСТВА ===== -->
            <section class="z-section">
                <div class="z-wrap">
                    <div class="z-sec-head z-center" data-reveal>
                        <div class="z-eyebrow">Почему ZLOCK</div>
                        <h2 class="z-h2">Упаковка, которая работает на ваш успех</h2>
                    </div>
                    <div class="z-adv-grid">
                        <?php
                        $adv = [
                            ['ph-factory', 'Собственное производство', 'Полный контроль качества на всех этапах — от сырья до отгрузки.'],
                            ['ph-gauge', 'Высокая скорость', 'Изготовление от 1 дня. Срочные заказы — в приоритете.'],
                            ['ph-paint-roller', 'Любой дизайн', 'Печать в 4 цвета, тиснение, индивидуальная разработка макета.'],
                            ['ph-truck', 'Доставка по РФ', 'Работаем с проверенными транспортными компаниями по всей стране.'],
                            ['ph-headset', 'Персональный менеджер', 'Сопровождение заказа от расчёта до доставки на склад.'],
                            ['ph-shield-check', 'Гарантия качества', 'Используем только сертифицированные материалы EVA и ПВД.'],
                        ];
                        foreach ($adv as $a): ?>
                        <div class="z-card z-lift" data-reveal>
                            <span class="z-ico"><i class="ph <?= $a[0] ?>"></i></span>
                            <h3 class="z-h3"><?= htmlspecialchars($a[1]) ?></h3>
                            <p><?= htmlspecialchars($a[2]) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- ===== МАТЕРИАЛЫ ===== -->
            <section id="materials" class="z-section">
                <div class="z-wrap">
                    <div class="z-sec-head z-center" data-reveal>
                        <div class="z-eyebrow">Материалы</div>
                        <h2 class="z-h2">Два материала под вашу задачу</h2>
                    </div>
                    <div class="z-mat-grid">
                        <div class="z-mat-card z-lift" data-reveal>
                            <div class="z-mat-badge gold">Премиум</div>
                            <div class="z-mat-photo"><img src="/images/eva.png" alt="EVA матовый ZIP-пакет" loading="lazy"></div>
                            <span class="z-ico"><i class="ph ph-snowflake"></i></span>
                            <h3 class="z-h3" style="font-size:26px">EVA — матовый</h3>
                            <p style="color:var(--z-text-2);margin:6px 0 0">Мягкий, благородный матовый эффект</p>
                            <div class="z-mat-list">
                                <div><i class="ph ph-check"></i>Мягкий и гибкий</div>
                                <div><i class="ph ph-check"></i>Устойчив к морозу</div>
                                <div><i class="ph ph-check"></i>Прочный на разрыв</div>
                                <div><i class="ph ph-check"></i>Матовая поверхность</div>
                            </div>
                            <div class="z-mat-price"><span style="font-size:13px;color:var(--z-text-2)">на 300к+ шт</span><span class="p z-tnum">от 1,5 ₽<small>/шт</small></span></div>
                        </div>
                        <div class="z-vs"><span>VS</span></div>
                        <div class="z-mat-card z-lift" data-reveal>
                            <div class="z-mat-badge mint">Популярный</div>
                            <div class="z-mat-photo"><img src="/images/pvd.png" alt="ПВД прозрачный ZIP-пакет" loading="lazy"></div>
                            <span class="z-ico"><i class="ph ph-eye"></i></span>
                            <h3 class="z-h3" style="font-size:26px">ПВД — прозрачный</h3>
                            <p style="color:var(--z-text-2);margin:6px 0 0">Кристальная прозрачность и блеск</p>
                            <div class="z-mat-list">
                                <div><i class="ph ph-check"></i>Кристальная прозрачность</div>
                                <div><i class="ph ph-check"></i>Блестящая поверхность</div>
                                <div><i class="ph ph-check"></i>Экономичный вариант</div>
                                <div><i class="ph ph-check"></i>Идеальная видимость товара</div>
                            </div>
                            <div class="z-mat-price"><span style="font-size:13px;color:var(--z-text-2)">на 300к+ шт</span><span class="p z-tnum">от 0,35 ₽<small>/шт</small></span></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== РАСПРОДАЖА / КАТАЛОГ ===== -->
            <section id="products" class="z-section">
                <div id="z-sale" data-end="<?= (int)$zSaleEnd ?>" class="z-wrap">
                    <div class="z-sec-head z-center" data-reveal>
                        <div class="z-sale-badge"><i class="ph ph-lightning"></i>Специальная распродажа</div>
                        <h2 class="z-h2">Пакеты по специальным ценам на объём</h2>
                        <p class="z-lead" style="margin-top:12px">Распродажа остатков производства. Количество ограничено!</p>
                    </div>

                    <div class="z-timer z-tnum" data-reveal>
                        <span class="lead-lbl"><i class="ph ph-clock"></i>Предложение действует ещё:</span>
                        <div class="z-tcell"><b id="t-days">00</b><small>дней</small></div>
                        <span class="z-tsep">:</span>
                        <div class="z-tcell"><b id="t-hours">00</b><small>часов</small></div>
                        <span class="z-tsep">:</span>
                        <div class="z-tcell"><b id="t-mins">00</b><small>минут</small></div>
                        <span class="z-tsep">:</span>
                        <div class="z-tcell sec"><b id="t-secs">00</b><small>секунд</small></div>
                    </div>

                    <div class="z-tiers" data-reveal>
                        <span class="z-tier on">Опт от 300 000 шт</span>
                        <span class="z-tier">Опт от 20 000 шт</span>
                        <span class="z-tier">Розница от 3 000 шт</span>
                    </div>

                    <h3 class="z-subhead" data-reveal><i class="ph ph-sliders-horizontal"></i>Слайдеры с бегунком<span class="z-tag gold">Хит продаж</span></h3>
                    <div class="z-prod-grid">
                        <?php if ($zSlidersTop): foreach ($zSlidersTop as $r) echo z_card($r, true, $zSliderMax);
                        else: ?><p class="z-lead">Скоро в наличии.</p><?php endif; ?>
                    </div>

                    <h3 class="z-subhead" data-reveal><i class="ph ph-lock-key"></i>ZIP-LOCK пакеты (грипперы)<span class="z-tag mint">Большой выбор</span></h3>
                    <div class="z-prod-grid" style="margin-bottom:0">
                        <?php if ($zGrippersTop): foreach ($zGrippersTop as $r) echo z_card($r, false, $zGripperMax);
                        else: ?><p class="z-lead">Скоро в наличии.</p><?php endif; ?>
                    </div>

                    <div class="z-center" style="margin-top:40px" data-reveal>
                        <a href="/katalog_zip_paketov/" class="z-btn z-btn-gold z-shine"><i class="ph ph-storefront"></i>Открыть весь каталог<i class="ph ph-arrow-right"></i></a>
                        <p style="color:var(--z-text-2);margin-top:12px;font-size:14px">Более <?= count($zSliders) + count($zGrippers) ?> позиций · фильтры по размеру/толщине · оптовые цены</p>
                    </div>
                </div>
            </section>

            <!-- ===== КАЛЬКУЛЯТОР + TELEGRAM ===== -->
            <section class="z-section">
                <div class="z-wrap">
                    <div class="z-calc-grid" data-reveal>
                        <div class="z-calc-card">
                            <div class="orb"></div>
                            <div style="position:relative">
                                <div class="z-eyebrow">Калькулятор</div>
                                <h2 class="z-h2" style="font-size:clamp(26px,3.4vw,36px);margin-bottom:16px">Рассчитайте свой тираж за 10 минут</h2>
                                <p style="font-size:16px;line-height:1.6;color:#B7CCE3;margin-bottom:28px;max-width:440px">Укажите размер, материал и тираж — менеджер пришлёт точную стоимость и бесплатный образец.</p>
                                <div class="z-steps">
                                    <div class="z-step"><span class="n">1</span><span>Выбираете размер и толщину</span></div>
                                    <div class="z-step"><span class="n">2</span><span>Указываете материал и тираж</span></div>
                                    <div class="z-step"><span class="n">3</span><span>Получаете расчёт и образец</span></div>
                                </div>
                                <a href="#calculator" class="z-btn z-btn-gold z-shine"><i class="ph ph-calculator"></i>Открыть калькулятор</a>
                            </div>
                        </div>
                        <div class="z-tg-card">
                            <span class="z-tg-ico"><i class="ph ph-telegram-logo"></i></span>
                            <h3 class="z-h3" style="font-size:25px;margin-bottom:12px">Мгновенный расчёт в Telegram</h3>
                            <p style="font-size:15.5px;line-height:1.6;color:#B7CCE3;margin-bottom:8px">Быстрее, чем через менеджера. Запустите бота и получите стоимость и КП в один клик.</p>
                            <div class="z-tg-chips">
                                <div><i class="ph ph-lightning"></i>24/7</div>
                                <div><i class="ph ph-robot"></i>Авторасчёт</div>
                                <div><i class="ph ph-file-text"></i>КП в клик</div>
                            </div>
                            <a href="https://t.me/zlock_sales_bot" target="_blank" rel="noopener noreferrer" class="z-btn z-btn-tg"><i class="ph ph-telegram-logo"></i>@zlock_sales_bot</a>
                        </div>
                    </div>

                    <!-- Рабочий калькулятор (бэкенд сохранён) -->
                    <span id="calculator" class="z-calc-anchor"></span>
                    <div class="z-calc-widget" data-reveal>
                        <div class="z-calc-host">
                            <section class="calculator-section">
                                <div class="container">
                                    <?php
                                    require_once 'includes/calculator.php';
                                    echo displayCalculatorForm();
                                    ?>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== ОТЗЫВЫ / ДОВЕРИЕ (⚠️ ЗАГЛУШКИ — заменить на реальные) ===== -->
            <section class="z-section">
                <div class="z-wrap">
                    <div class="z-sec-head z-center" data-reveal>
                        <div class="z-eyebrow">Отзывы</div>
                        <h2 class="z-h2">Нам доверяют оптовые покупатели</h2>
                    </div>
                    <div class="z-adv-grid">
                        <?php
                        // ⚠️ ЗАГЛУШКИ отзывов — заменить на реальные (имя, компания, текст)
                        $reviews = [
                            ['Алексей М.', 'оптовый покупатель', 'Заказывали партию слайдеров под фасовку — приехало в срок, качество отличное. Менеджер на связи, пересчитал цену под наш объём.'],
                            ['ООО «Пример»', 'производство продуктов', 'Берём грипперы регулярно. Удобно, что можно по счёту для юрлица. Цена на объём приятная.'],
                            ['Ирина К.', 'маркетплейс-селлер', 'Нужна была упаковка с печатью — сделали образец бесплатно, потом тираж. Рекомендую.'],
                        ];
                        foreach ($reviews as $r): ?>
                        <div class="z-card z-lift" data-reveal>
                            <div style="color:var(--z-gold);margin-bottom:10px;font-size:14px">★★★★★</div>
                            <p style="margin:0 0 16px"><?= htmlspecialchars($r[2]) ?></p>
                            <div style="display:flex;align-items:center;gap:12px">
                                <span class="z-ico" style="width:42px;height:42px;font-size:18px;margin:0"><i class="ph ph-user"></i></span>
                                <div>
                                    <div style="font-weight:700;color:#fff"><?= htmlspecialchars($r[0]) ?></div>
                                    <div style="font-size:13px;color:var(--z-text-3)"><?= htmlspecialchars($r[1]) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Гарантии / сертификаты -->
                    <div class="z-glass" style="margin-top:24px;padding:22px 26px;display:flex;gap:28px;flex-wrap:wrap;justify-content:center;align-items:center" data-reveal>
                        <span style="display:flex;align-items:center;gap:9px;color:var(--z-text-2)"><i class="ph ph-shield-check" style="color:var(--z-mint);font-size:20px"></i> Сертифицированные материалы EVA/ПВД</span>
                        <span style="display:flex;align-items:center;gap:9px;color:var(--z-text-2)"><i class="ph ph-factory" style="color:var(--z-mint);font-size:20px"></i> Собственное производство</span>
                        <span style="display:flex;align-items:center;gap:9px;color:var(--z-text-2)"><i class="ph ph-arrows-clockwise" style="color:var(--z-mint);font-size:20px"></i> Возврат по закону</span>
                        <span style="display:flex;align-items:center;gap:9px;color:var(--z-text-2)"><i class="ph ph-receipt" style="color:var(--z-mint);font-size:20px"></i> Чек и закрывающие документы</span>
                    </div>
                </div>
            </section>

            <!-- ===== КОНТАКТЫ + ФОРМА (бэкенд #leadForm сохранён) ===== -->
            <section id="contact" class="z-section">
                <div class="z-wrap">
                    <div class="z-glass z-contact" data-reveal>
                        <div>
                            <h2 class="z-h2" style="font-size:clamp(26px,3.6vw,38px);margin-bottom:16px">Получите расчёт и образец бесплатно</h2>
                            <p style="font-size:16px;line-height:1.6;color:var(--z-text-2);margin-bottom:30px">Оставьте контакты — менеджер свяжется в течение 10 минут в рабочее время.</p>
                            <a href="tel:+79203465067" class="z-contact-link"><span class="ic" style="background:rgba(95,227,208,.14);color:#5FE3D0"><i class="ph ph-phone"></i></span><span><small>Телефон</small><b>+7 (920) 346-50-67</b></span></a>
                            <a href="https://t.me/zlock_sales_bot" target="_blank" rel="noopener noreferrer" class="z-contact-link"><span class="ic" style="background:rgba(34,158,217,.16);color:#5FB9E8"><i class="ph ph-telegram-logo"></i></span><span><small>Telegram</small><b>@zlock_sales_bot</b></span></a>
                            <a href="mailto:ZTR37@Bk.ru" class="z-contact-link"><span class="ic" style="background:rgba(95,227,208,.14);color:#5FE3D0"><i class="ph ph-envelope-simple"></i></span><span><small>Email</small><b>ZTR37@Bk.ru</b></span></a>
                        </div>
                        <form id="leadForm" class="z-form">
                            <input type="text" name="name" placeholder="Ваше имя *" required>
                            <input type="tel" name="phone" placeholder="Телефон *" required>
                            <input type="email" name="email" placeholder="Email">
                            <textarea name="message" placeholder="Размер, материал, тираж" rows="3"></textarea>
                            <input type="hidden" id="recaptchaToken" name="recaptcha_token">
                            <label class="z-consent"><input type="checkbox" name="pdn_consent" value="1" required> Я даю <a href="/polconf.html" target="_blank" style="color:var(--z-mint)">согласие на обработку персональных данных</a></label>
                            <button type="submit" class="z-btn z-btn-gold z-shine"><i class="ph ph-paper-plane-tilt"></i>Получить расчёт</button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- ===== SEO-БЛОК АССОРТИМЕНТА ===== -->
            <section class="z-section seo-products-section">
                <div class="z-wrap">
                    <div class="z-sec-head z-center" data-reveal>
                        <div class="z-eyebrow">Ассортимент</div>
                        <h2 class="z-h2">Производство ZIP-пакетов оптом</h2>
                    </div>
                    <div class="z-adv-grid" style="grid-template-columns:1fr 1fr">
                        <div class="z-card" data-reveal>
                            <h3 class="z-h3" style="margin-bottom:14px"><i class="ph ph-sliders-horizontal" style="color:var(--z-mint)"></i> Пакеты с замком слайдер</h3>
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <?php foreach (array_slice($zSliders, 0, 10) as $r): ?>
                                <a class="seo-product-link" href="/product/<?= (int)$r['id'] ?>" style="text-decoration:none"><i class="ph ph-check" style="color:var(--z-mint);margin-right:8px"></i><?= htmlspecialchars($r['short_name'] ?: $r['full_name']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="z-card" data-reveal>
                            <h3 class="z-h3" style="margin-bottom:14px"><i class="ph ph-lock-key" style="color:var(--z-mint)"></i> Пакеты с замком ZIP-LOCK</h3>
                            <div style="display:flex;flex-direction:column;gap:8px">
                                <?php foreach (array_slice($zGrippers, 0, 10) as $r): ?>
                                <a class="seo-product-link" href="/product/<?= (int)$r['id'] ?>" style="text-decoration:none"><i class="ph ph-check" style="color:var(--z-mint);margin-right:8px"></i><?= htmlspecialchars($r['short_name'] ?: $r['full_name']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="z-glass" style="margin-top:26px;padding:30px" data-reveal>
                        <p style="color:var(--z-text-2);line-height:1.7;max-width:900px">
                            Компания ZLOCK специализируется на производстве и оптовой продаже упаковочных пакетов
                            с замком типа слайдер и zip-lock. В ассортименте — пакеты различных размеров: от 15×20 см
                            до 35×45 см, с толщиной от 35 до 100 мкм, прозрачные и матовые ПВД. Работаем с розничными
                            и оптовыми клиентами, предоставляя индивидуальные условия сотрудничества.
                        </p>
                        <div style="margin-top:18px;display:flex;flex-wrap:wrap;gap:10px">
                            <?php foreach (['zip пакеты оптом','пакеты с замком слайдер','пвд пакеты производство','zip-lock пакеты москва','упаковочные пакеты оптом'] as $kw): ?>
                            <span class="z-tier"><?= htmlspecialchars($kw) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Блок с политикой cookie (фиксированный внизу экрана) -->
        <div id="cookieConsent" class="cookie-consent" style="display: none;">
            <div class="container">
                <div class="cookie-content">
                    <div class="cookie-text">
                        <i class="fas fa-cookie-bite"></i>
                        <p>Мы используем файлы cookie для улучшения работы сайта и предоставления вам наилучшего сервиса.
                           Продолжая использовать сайт, вы соглашаетесь с
                           <a href="/polconf.html" target="_blank">Политикой конфиденциальности</a> и
                           <a href="/cookie-policy.php" target="_blank">Политикой использования cookie</a>.
                        </p>
                    </div>
                    <div class="cookie-actions">
                        <button id="acceptCookies" class="btn btn-primary btn-sm">
                            <i class="fas fa-check"></i> Принять все
                        </button>
                        <button id="rejectCookies" class="btn btn-outline btn-sm">
                            <i class="fas fa-times"></i> Отклонить
                        </button>
                        <button id="customizeCookies" class="btn btn-link btn-sm">
                            <i class="fas fa-cog"></i> Настроить
                        </button>
                    </div>
                </div>

                <!-- Дополнительные настройки cookie (по умолчанию скрыто) -->
                <div id="cookieSettings" class="cookie-settings" style="display: none;">
                    <div class="settings-content">
                        <h4><i class="fas fa-sliders-h"></i> Настройки cookie</h4>
                        <div class="setting-option">
                            <label class="switch">
                                <input type="checkbox" id="necessaryCookies" checked disabled>
                                <span class="slider"></span>
                            </label>
                            <div class="setting-info">
                                <strong>Необходимые cookies</strong>
                                <p>Обязательные для работы сайта. Не могут быть отключены.</p>
                            </div>
                        </div>
                        <div class="setting-option">
                            <label class="switch">
                                <input type="checkbox" id="analyticsCookies" checked>
                                <span class="slider"></span>
                            </label>
                            <div class="setting-info">
                                <strong>Аналитические cookies</strong>
                                <p>Помогают улучшать сайт, собирая анонимную статистику.</p>
                            </div>
                        </div>
                        <div class="setting-option">
                            <label class="switch">
                                <input type="checkbox" id="marketingCookies">
                                <span class="slider"></span>
                            </label>
                            <div class="setting-info">
                                <strong>Маркетинговые cookies</strong>
                                <p>Используются для показа релевантной рекламы.</p>
                            </div>
                        </div>
                        <div class="settings-actions">
                            <button id="saveCookieSettings" class="btn btn-primary btn-sm">
                                <i class="fas fa-save"></i> Сохранить настройки
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно заказа звонка -->
        <div class="modal" id="callbackModal">
            <div class="modal-content">
                <button class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
                <h3>Заказать обратный звонок</h3>
                <form id="callbackForm">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Ваше имя *" required>
                    </div>
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="Телефон *" required>
                    </div>
                    <input type="hidden" id="callbackRecaptchaToken" name="recaptcha_token">
                    <div class="form-group">
                        <textarea name="message" placeholder="Комментарий (необязательно)" rows="3"></textarea>
                    </div>
                    <label class="z-consent"><input type="checkbox" name="pdn_consent" value="1" required> Я даю <a href="/polconf.html" target="_blank">согласие на обработку персональных данных</a></label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-phone"></i> Заказать звонок
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <?php include 'footer.php'; ?>
        </div><!-- /.z-content -->
    </div><!-- /.site-wrapper -->

    <script src="/js/script.js"></script>
    <script src="/js/cart.js"></script>
    <script src="/js/home.js"></script>

    <script>
    // Инициализация reCAPTCHA
    grecaptcha.ready(function() {
        console.log('reCAPTCHA готов к работе');
    });

    // Управление cookie-баннером
    document.addEventListener('DOMContentLoaded', function() {
        const cookieConsent = document.getElementById('cookieConsent');
        const acceptCookiesBtn = document.getElementById('acceptCookies');
        const rejectCookiesBtn = document.getElementById('rejectCookies');
        const customizeCookiesBtn = document.getElementById('customizeCookies');
        const cookieSettings = document.getElementById('cookieSettings');
        const saveCookieSettingsBtn = document.getElementById('saveCookieSettings');

        if (!localStorage.getItem('cookiesAccepted')) {
            setTimeout(() => {
                if (cookieConsent) {
                    cookieConsent.style.display = 'block';
                    setTimeout(() => { cookieConsent.classList.add('show'); }, 100);
                }
            }, 1500);
        } else {
            if (cookieConsent) { cookieConsent.style.display = 'none'; }
        }

        if (acceptCookiesBtn) {
            acceptCookiesBtn.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                localStorage.setItem('cookiesAccepted', 'all');
                localStorage.setItem('analyticsCookies', 'true');
                localStorage.setItem('marketingCookies', 'true');
                if (cookieConsent) {
                    cookieConsent.classList.remove('show');
                    setTimeout(() => { cookieConsent.style.display = 'none'; }, 400);
                }
                setTimeout(() => { location.reload(); }, 500);
            });
        }

        if (rejectCookiesBtn) {
            rejectCookiesBtn.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                localStorage.setItem('cookiesAccepted', 'none');
                localStorage.setItem('analyticsCookies', 'false');
                localStorage.setItem('marketingCookies', 'false');
                if (cookieConsent) {
                    cookieConsent.classList.remove('show');
                    setTimeout(() => { cookieConsent.style.display = 'none'; }, 400);
                }
            });
        }

        if (customizeCookiesBtn) {
            customizeCookiesBtn.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                if (cookieSettings) {
                    cookieSettings.style.display = (cookieSettings.style.display === 'none' || cookieSettings.style.display === '') ? 'block' : 'none';
                }
            });
        }

        if (saveCookieSettingsBtn) {
            saveCookieSettingsBtn.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                const analytics = document.getElementById('analyticsCookies')?.checked || false;
                const marketing = document.getElementById('marketingCookies')?.checked || false;
                localStorage.setItem('cookiesAccepted', 'custom');
                localStorage.setItem('analyticsCookies', analytics);
                localStorage.setItem('marketingCookies', marketing);
                if (cookieSettings) { cookieSettings.style.display = 'none'; }
                if (cookieConsent) {
                    cookieConsent.classList.remove('show');
                    setTimeout(() => { cookieConsent.style.display = 'none'; }, 400);
                }
            });
        }

        const savedAnalytics = localStorage.getItem('analyticsCookies');
        const savedMarketing = localStorage.getItem('marketingCookies');
        if (savedAnalytics !== null) {
            const analyticsCheckbox = document.getElementById('analyticsCookies');
            if (analyticsCheckbox) { analyticsCheckbox.checked = savedAnalytics === 'true'; }
        }
        if (savedMarketing !== null) {
            const marketingCheckbox = document.getElementById('marketingCookies');
            if (marketingCheckbox) { marketingCheckbox.checked = savedMarketing === 'true'; }
        }
    });

    // Обработчики reCAPTCHA для форм
    window.addEventListener('load', function() {
        const leadForm = document.getElementById('leadForm');
        if (leadForm && !leadForm.hasAttribute('data-handler-attached')) {
            leadForm.setAttribute('data-handler-attached', 'true');
            leadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const phone = this.querySelector('input[name="phone"]');
                const name = this.querySelector('input[name="name"]');
                if (!phone || !phone.value.trim()) { alert('Пожалуйста, введите телефон'); return; }
                if (!name || !name.value.trim()) { alert('Пожалуйста, введите имя'); return; }
                if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', {action: 'submit'}).then(function(token) {
                            const tokenInput = document.getElementById('recaptchaToken');
                            if (tokenInput) { tokenInput.value = token; }
                            leadForm.submit();
                        });
                    });
                } else {
                    console.error('reCAPTCHA не загружена');
                    leadForm.submit();
                }
            });
        }

        const callbackForm = document.getElementById('callbackForm');
        if (callbackForm && !callbackForm.hasAttribute('data-handler-attached')) {
            callbackForm.setAttribute('data-handler-attached', 'true');
            callbackForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const phone = this.querySelector('input[name="phone"]');
                if (!phone || !phone.value.trim()) { alert('Пожалуйста, введите телефон'); return; }
                if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', {action: 'callback'}).then(function(token) {
                            const tokenInput = document.getElementById('callbackRecaptchaToken');
                            if (tokenInput) { tokenInput.value = token; }
                            callbackForm.submit();
                        });
                    });
                } else {
                    console.error('reCAPTCHA не загружена');
                    callbackForm.submit();
                }
            });
        }
    });

    // Автозаполнение калькулятора по ссылкам ассортимента (если ведут на якорь)
    document.addEventListener('DOMContentLoaded', function() {
        const productLinks = document.querySelectorAll('.seo-product-link[href^="#"]');
        productLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const type = this.getAttribute('data-type');
                const width = this.getAttribute('data-width');
                const height = this.getAttribute('data-height');
                const thickness = this.getAttribute('data-thickness');
                const calculatorSection = document.getElementById('calculator');
                if (calculatorSection) { calculatorSection.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                setTimeout(() => { fillCalculatorForm(type, width, height, thickness); }, 500);
            });
        });

        function fillCalculatorForm(type, width, height, thickness) {
            const typeSelect = document.getElementById('type');
            const widthInput = document.getElementById('width');
            const heightInput = document.getElementById('height');
            const thicknessSelect = document.getElementById('thickness');
            if (typeSelect && type) {
                for (let option of typeSelect.options) {
                    if (option.value === type || option.text.toLowerCase().includes(type)) { typeSelect.value = option.value; break; }
                }
                typeSelect.dispatchEvent(new Event('change'));
            }
            if (widthInput && width) { widthInput.value = width; widthInput.dispatchEvent(new Event('input')); }
            if (heightInput && height) { heightInput.value = height; heightInput.dispatchEvent(new Event('input')); }
            if (thicknessSelect && thickness) {
                for (let option of thicknessSelect.options) {
                    if (parseInt(option.value) === parseInt(thickness)) { thicknessSelect.value = option.value; break; }
                }
                thicknessSelect.dispatchEvent(new Event('change'));
            }
        }
    });
    </script>

    <!-- Yandex.Metrika counter -->
    <script>
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=106644271', 'ym');

        ym(106644271, 'init', {ssr:true, webvisor:true, trackHash:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/106644271" style="position:absolute; left:-9999px;" alt=""></div></noscript>
    <!-- /Yandex.Metrika counter -->
</body>
</html>
