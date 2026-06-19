<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/catalog_functions.php';
require_once __DIR__ . '/includes/product_view.php';
require_once __DIR__ . '/includes/seo.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$catalog = new Catalog();
$product = $id ? $catalog->getProductById($id) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Товар не найден';
} else {
    $pageTitle = $product['meta_title'] ?: ($product['full_name'] . ' — купить оптом | ZLOCK');
}

$minQty  = (int)($product['min_order_qty'] ?? 1);
$qtyStep = (int)($product['qty_step'] ?? 1);
$hasPrice = $product && $product['price_rub'] !== null && (float)$product['price_rub'] > 0;
$priceVal = $hasPrice ? (float)$product['price_rub'] : 0.0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php if ($product): ?>
    <meta name="description" content="<?= htmlspecialchars($product['meta_description'] ?: $product['full_name']) ?>">
    <link rel="canonical" href="https://zippaket-optom.ru/product/<?= (int)$product['id'] ?>">
    <?php endif; ?>
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/catalog.css">
    <link rel="stylesheet" href="/css/premium.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/shop-dark.css">
    <?php if ($product):
        $stock = pv_stock_status((int)$product['stock_quantity']);
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product['full_name'],
            'image' => (strpos($product['image_url'], 'no-image') === false) ? 'https://zippaket-optom.ru' . $product['image_url'] : null,
            'description' => $product['meta_description'] ?: $product['full_name'],
            'offers' => array_filter([
                '@type' => 'Offer',
                'price' => $hasPrice ? number_format($priceVal, 2, '.', '') : null,
                'priceCurrency' => 'RUB',
                'availability' => $stock['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder',
            ], static fn($v) => $v !== null),
        ];
        $ld = array_filter($ld, static fn($v) => $v !== null);
    ?>
    <script type="application/ld+json">
    <?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <?php
        $crumbs = [
            ['name' => 'Главная', 'url' => '/'],
            ['name' => 'Каталог', 'url' => '/katalog_zip_paketov/'],
        ];
        if (!empty($product['category'])) {
            $crumbs[] = ['name' => $product['category'], 'url' => '/katalog_zip_paketov/?category=' . rawurlencode($product['category'])];
        }
        $crumbs[] = ['name' => ($product['short_name'] ?: $product['full_name']), 'url' => '/product/' . (int)$product['id']];
    ?>
    <script type="application/ld+json">
    <?= seo_breadcrumb_jsonld($crumbs) ?>
    </script>
    <?php endif; ?>
</head>
<body class="premium zlock">
    <div class="site-wrapper">
        <?php include __DIR__ . '/header.php'; ?>
        <main class="main-content">
        <?php if (!$product): ?>
            <section class="catalog-header-section"><div class="container">
                <div class="no-products">
                    <i class="fas fa-box-open"></i>
                    <h1>Товар не найден</h1>
                    <p>Возможно, он снят с продажи.</p>
                    <a href="/katalog_zip_paketov" class="btn btn-primary">В каталог</a>
                </div>
            </div></section>
        <?php else:
            $size = pv_format_size($product['width'] !== null ? (int)$product['width'] : null, $product['height'] !== null ? (int)$product['height'] : null);
        ?>
            <div class="breadcrumbs-section"><div class="container">
                <div class="breadcrumbs">
                    <a href="/">Главная</a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <a href="/katalog_zip_paketov">Каталог</a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span class="current"><?= htmlspecialchars($product['short_name'] ?: $product['full_name']) ?></span>
                </div>
            </div></div>

            <?php
                // Материал по цвету; похожие товары той же категории
                $material = (mb_stripos((string)$product['color'], 'мат') !== false) ? 'EVA (матовый)'
                          : ((mb_stripos((string)$product['color'], 'прозр') !== false) ? 'ПВД (прозрачный)' : '');
                $isSlider = mb_stripos((string)$product['category'], 'слайдер') !== false;
                $heroImg = $isSlider
                    ? ((mb_stripos((string)$product['color'], 'мат') !== false) ? '/images/eva.png' : '/images/pvd.png')
                    : '/images/gripper.jpg';
                $related = [];
                try {
                    $rel = $catalog->getProducts(['category' => $product['category']], 1, 6);
                    $relList = $rel['products'] ?? (is_array($rel) ? $rel : []);
                    foreach ($relList as $rp) {
                        if ((int)$rp['id'] !== (int)$product['id']) { $related[] = $rp; }
                        if (count($related) >= 4) break;
                    }
                } catch (Throwable $e) {}
            ?>
            <section class="catalog-section"><div class="container">
                <div class="product-page" style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-start">
                    <div class="product-page-image" style="flex:1 1 320px;max-width:440px">
                        <img src="<?= htmlspecialchars($heroImg) ?>" alt="<?= htmlspecialchars($product['full_name']) ?>" style="width:100%;border-radius:16px;display:block">
                    </div>
                    <div class="product-page-info" style="flex:2 1 360px">
                        <h1 style="margin-top:0"><?= htmlspecialchars($product['full_name']) ?></h1>
                        <div class="product-specs" style="margin:14px 0;display:flex;gap:8px;flex-wrap:wrap">
                            <?php if ($size): ?><span class="spec-item"><i class="fas fa-ruler-combined"></i> <?= $size ?></span><?php endif; ?>
                            <?php if (!empty($product['thickness'])): ?><span class="spec-item"><i class="fas fa-layer-group"></i> <?= (int)$product['thickness'] ?> мкм</span><?php endif; ?>
                            <?php if (!empty($product['color'])): ?><span class="spec-item"><i class="fas fa-palette"></i> <?= htmlspecialchars($product['color']) ?></span><?php endif; ?>
                        </div>
                        <div class="product-pricing z-tnum" style="margin:16px 0">
                        <?php if ($hasPrice): ?>
                            <span class="current-price" style="font-size:2rem;font-weight:800"><?= pv_format_price($priceVal) ?></span>
                            <span class="price-unit">/ шт</span>
                        <?php else: ?>
                            <span class="current-price" style="font-size:1.4rem;font-weight:800">Цена по запросу</span>
                        <?php endif; ?>
                        </div>
                        <div class="stock-info <?= $stock['in_stock'] ? 'in-stock' : 'out-of-stock' ?>" style="margin:10px 0">
                            <i class="fas <?= $stock['in_stock'] ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                            <?= $stock['label'] ?><?= $stock['count_label'] ? ': ' . $stock['count_label'] : '' ?>
                        </div>
                        <div class="pack-note" style="font-size:0.9rem;margin-bottom:16px">
                            <?= htmlspecialchars(pv_pack_note($minQty, $qtyStep)) ?><?= !empty($product['pack_label']) ? ' (' . htmlspecialchars($product['pack_label']) . ')' : '' ?>
                        </div>
                        <div class="product-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                            <input type="number" id="qty" class="filter-input small" value="<?= pv_default_qty($minQty, $qtyStep) ?>" min="<?= $minQty ?>" step="<?= $qtyStep ?>" style="width:110px">
                            <button class="btn btn-primary js-cart-add"
                                    data-id="<?= (int)$product['id'] ?>"
                                    data-name="<?= htmlspecialchars($product['full_name']) ?>"
                                    data-price="<?= htmlspecialchars((string)($hasPrice ? $priceVal : 0)) ?>"
                                    data-min="<?= $minQty ?>"
                                    data-step="<?= $qtyStep ?>">
                                <i class="fas fa-shopping-cart"></i> В корзину
                            </button>
                            <a href="/index.php#calculator" class="btn btn-outline">
                                <i class="fas fa-calculator"></i> С логотипом? Рассчитать
                            </a>
                        </div>
                        <!-- Блок доверия -->
                        <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:22px;padding-top:18px;border-top:1px solid var(--z-hairline,#e6ecf3)">
                            <span style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--z-text-2,#64748b)"><i class="fas fa-industry" style="color:var(--z-mint,#0A8F8F)"></i> Своё производство</span>
                            <span style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--z-text-2,#64748b)"><i class="fas fa-truck" style="color:var(--z-mint,#0A8F8F)"></i> Доставка по РФ</span>
                            <span style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--z-text-2,#64748b)"><i class="fas fa-gift" style="color:var(--z-mint,#0A8F8F)"></i> Бесплатный образец</span>
                        </div>
                    </div>
                </div>

                <!-- Характеристики -->
                <div class="z-glass" style="padding:24px 26px;margin-top:26px;max-width:680px">
                    <h2 class="z-h3" style="margin:0 0 14px;font-size:1.25rem">Характеристики</h2>
                    <table style="width:100%;border-collapse:collapse;font-size:14px">
                        <?php
                        $specs = array_filter([
                            'Размер' => $size,
                            'Толщина' => !empty($product['thickness']) ? (int)$product['thickness'] . ' мкм' : '',
                            'Цвет' => $product['color'] ?? '',
                            'Материал' => $material,
                            'Упаковка' => pv_pack_note($minQty, $qtyStep),
                            'Категория' => $product['category'] ?? '',
                        ]);
                        foreach ($specs as $k => $v): ?>
                        <tr>
                            <td style="padding:9px 0;color:var(--z-text-2,#64748b);width:40%;border-bottom:1px solid var(--z-hairline,#eef2f6)"><?= htmlspecialchars($k) ?></td>
                            <td style="padding:9px 0;font-weight:600;border-bottom:1px solid var(--z-hairline,#eef2f6)"><?= htmlspecialchars((string)$v) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <!-- Похожие товары -->
                <?php if ($related): ?>
                <h2 class="z-h2" style="margin:40px 0 20px;font-size:1.6rem">Похожие товары</h2>
                <div class="z-prod-grid">
                    <?php foreach ($related as $rp):
                        $rprice = (float)$rp['price_rub'];
                        $rimg = (mb_stripos((string)$rp['category'],'слайдер')!==false)
                            ? ((mb_stripos((string)$rp['color'],'мат')!==false)?'/images/eva.png':'/images/pvd.png') : '/images/gripper.jpg';
                    ?>
                    <article class="z-prod z-lift">
                        <a href="/product/<?= (int)$rp['id'] ?>" style="text-decoration:none;color:inherit">
                            <div class="z-prod-photo"><img src="<?= htmlspecialchars($rimg) ?>" alt="" loading="lazy"></div>
                            <div class="z-prod-size" style="font-size:18px"><?= htmlspecialchars($rp['short_name'] ?: $rp['full_name']) ?></div>
                        </a>
                        <div class="z-prices z-tnum" style="margin-top:12px">
                            <div class="row"><span>Цена</span><span class="p-main"><?= number_format($rprice,2,',',' ') ?> ₽/шт</span></div>
                        </div>
                        <button class="z-add js-cart-add" data-id="<?= (int)$rp['id'] ?>" data-name="<?= htmlspecialchars($rp['full_name']) ?>" data-price="<?= htmlspecialchars((string)$rprice) ?>" data-min="<?= (int)($rp['min_order_qty'] ?? 1) ?>" data-step="<?= (int)($rp['qty_step'] ?? 1) ?>">
                            <i class="fas fa-shopping-cart"></i> В корзину
                        </button>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div></section>
        <?php endif; ?>
        </main>
        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="/js/script.js"></script>
    <script src="/js/cart.js"></script>
</body>
</html>
