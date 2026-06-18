<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/catalog_functions.php';
require_once __DIR__ . '/../includes/product_view.php';
require_once __DIR__ . '/../includes/seo.php';

// Получаем параметры фильтрации
$filters = [
    'category' => $_GET['category'] ?? '',
    'type' => $_GET['type'] ?? '',
    'thickness' => $_GET['thickness'] ?? '',
    'color' => $_GET['color'] ?? '',
    'in_stock' => $_GET['in_stock'] ?? '',
    'min_width' => $_GET['min_width'] ?? '',
    'max_width' => $_GET['max_width'] ?? '',
    'min_height' => $_GET['min_height'] ?? '',
    'max_height' => $_GET['max_height'] ?? '',
    'search' => $_GET['search'] ?? '',
    'sort' => $_GET['sort'] ?? 'popular'
];

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;

$catalog = new Catalog();
$categories = $catalog->getCategories();
$thicknesses = $catalog->getThicknesses();
$colors = $catalog->getColors();
$result = $catalog->getProducts($filters, $page, $perPage);
$popularProducts = $catalog->getPopularProducts(4);
$specialOffers = $catalog->getSpecialOffers(4); // Показываем 4 спецпредложения

// Формируем заголовок страницы
$pageTitle = "Каталог ZIP-пакетов";
if (!empty($filters['search'])) {
    $pageTitle = "Поиск: " . htmlspecialchars($filters['search']);
} elseif (!empty($filters['category'])) {
    $pageTitle = htmlspecialchars($filters['category']);
}

// UTM трекер
if (file_exists(__DIR__ . '/../includes/utm_tracker.php')) {
    require_once __DIR__ . '/../includes/utm_tracker.php';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken()) ?>">
    <title><?= $pageTitle ?> | ZLOCK - Производство ZIP-пакетов</title>
    <meta name="description" content="Каталог ZIP-пакетов от производителя. Более 50 видов пакетов с замком слайдер и zip-lock. Любые размеры и толщина. Оптом и в розницу.">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= $pageTitle ?> | ZLOCK">
    <meta property="og:description" content="Каталог ZIP-пакетов от производителя. Любые размеры и толщина.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zippaket-optom.ru/katalog_zip_paketov">
    <meta property="og:image" content="https://zippaket-optom.ru/images/og-image.jpg">
    <?php
        $catCanonical = '/katalog_zip_paketov/' . (!empty($filters['category']) ? '?category=' . rawurlencode($filters['category']) : '');
        $crumbs = [
            ['name' => 'Главная', 'url' => '/'],
            ['name' => 'Каталог', 'url' => '/katalog_zip_paketov/'],
        ];
        if (!empty($filters['category'])) {
            $crumbs[] = ['name' => $filters['category'], 'url' => $catCanonical];
        }
    ?>
    <link rel="canonical" href="<?= htmlspecialchars(seo_url($catCanonical)) ?>">
    <script type="application/ld+json">
    <?= seo_breadcrumb_jsonld($crumbs) ?>
    </script>

    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/images/zlock.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/zlock.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/zlock.ico">
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    
    <!-- reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Основные стили -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/catalog.css">
    <link rel="stylesheet" href="/css/premium.css">

    <style>
        /* Компактные фильтры для ПК */
        .compact-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            background: white;
            border-radius: 20px;
            padding: 15px 20px;
            margin-bottom: 30px;
            border: 1px solid #eef2f6;
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
        }
        
        .compact-filter-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            padding: 8px 15px;
            border-radius: 40px;
            border: 1px solid #e2e8f0;
        }
        
        .compact-filter-item i {
            color: #3498db;
            font-size: 0.9rem;
        }
        
        .compact-filter-item select,
        .compact-filter-item input {
            border: none;
            background: transparent;
            padding: 5px;
            font-size: 0.95rem;
            color: #1e293b;
            outline: none;
            min-width: 120px;
        }
        
        .compact-filter-item input {
            width: 80px;
        }
        
        .compact-filter-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
        
        .compact-filter-actions .btn {
            padding: 8px 20px;
            font-size: 0.95rem;
        }
        
        /* Сетка для спецпредложений и популярных товаров */
        .compact-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .compact-card {
            background: white;
            border-radius: 16px;
            padding: 15px;
            border: 1px solid #eef2f6;
            transition: all 0.3s;
            position: relative;
        }
        
        .compact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        
        .compact-card.special {
            background: linear-gradient(135deg, #fef9e7 0%, #fff9e6 100%);
            border-color: #fde68a;
        }
        
        .compact-badge {
            position: absolute;
            top: -8px;
            right: 10px;
            background: #f59e0b;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            text-transform: uppercase;
        }
        
        .compact-image {
            width: 100%;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px;
        }
        
        .compact-image img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
        }
        
        .compact-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            line-height: 1.3;
            height: 2.6em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .compact-price {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .compact-stock {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 10px;
        }
        
        .compact-btn {
            width: 100%;
            padding: 8px;
            font-size: 0.9rem;
        }
        
        /* Мобильный фильтр */
        .filter-toggle {
            display: none;
            width: 100%;
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            color: #1e293b;
            align-items: center;
            justify-content: space-between;
        }
        
        .filter-toggle i {
            transition: transform 0.3s;
        }
        
        .filter-toggle.active i {
            transform: rotate(180deg);
        }
        
        .mobile-filters-panel {
            display: none;
            margin-bottom: 30px;
        }
        
        .mobile-filters-panel.show {
            display: block;
        }
        
        /* Адаптивность */
        @media (max-width: 1200px) {
            .compact-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .compact-filters {
                display: none;
            }
            
            .filter-toggle {
                display: flex;
            }
            
            .compact-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .compact-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="premium">
    <div class="site-wrapper">
        <!-- Header -->
        <?php include __DIR__ . '/../header.php'; ?>

        <main class="main-content">
            <!-- Хлебные крошки -->
            <div class="breadcrumbs-section">
                <div class="container">
                    <div class="breadcrumbs">
                        <a href="/">Главная</a>
                        <span class="separator"><i class="fas fa-chevron-right"></i></span>
                        <span class="current">Каталог ZIP-пакетов</span>
                    </div>
                </div>
            </div>

            <!-- Заголовок каталога -->
            <section class="catalog-header-section">
                <div class="container">
                    <div class="catalog-header">
                        <h1 class="catalog-title">
                            <?= $pageTitle ?>
                            <?php if ($result['total'] > 0): ?>
                                <span class="catalog-count">(<?= number_format($result['total'], 0, ',', ' ') ?> товаров)</span>
                            <?php endif; ?>
                        </h1>
                        <p class="catalog-description">
                            ZIP-пакеты от производителя с доставкой по всей России. В наличии и на заказ.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Основной контент каталога -->
            <section class="catalog-section">
                <div class="container">
                    <!-- Кнопка для мобильных фильтров -->
                    <div class="filter-toggle" id="filterToggle">
                        <span><i class="fas fa-sliders-h"></i> Показать фильтры</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    
                    <!-- Компактные фильтры для ПК -->
                    <div class="compact-filters">
                        <div class="compact-filter-item">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Поиск..." id="compactSearch" value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        
                        <div class="compact-filter-item">
                            <i class="fas fa-tag"></i>
                            <select id="compactCategory">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>" <?= $filters['category'] == $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="compact-filter-item">
                            <i class="fas fa-lock"></i>
                            <select id="compactType">
                                <option value="">Все типы</option>
                                <option value="slider" <?= $filters['type'] == 'slider' ? 'selected' : '' ?>>Слайдер</option>
                                <option value="ziplock" <?= $filters['type'] == 'ziplock' ? 'selected' : '' ?>>ZIP-LOCK</option>
                            </select>
                        </div>
                        
                        <div class="compact-filter-item">
                            <i class="fas fa-ruler"></i>
                            <select id="compactThickness">
                                <option value="">Любая толщина</option>
                                <?php foreach ($thicknesses as $thickness): ?>
                                    <option value="<?= $thickness ?>" <?= $filters['thickness'] == $thickness ? 'selected' : '' ?>><?= $thickness ?> мкм</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="compact-filter-actions">
                            <button class="btn btn-primary" id="compactApplyFilters">
                                <i class="fas fa-search"></i> Применить
                            </button>
                            <a href="/katalog_zip_paketov" class="btn btn-outline">
                                <i class="fas fa-undo-alt"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Мобильная панель фильтров (скрыта по умолчанию) -->
                    <div class="mobile-filters-panel" id="mobileFiltersPanel">
                        <div class="filter-block">
                            <div class="filter-header">
                                <h3><i class="fas fa-filter"></i> Фильтры</h3>
                                <a href="/katalog_zip_paketov" class="reset-filters">
                                    <i class="fas fa-undo-alt"></i> Сбросить
                                </a>
                            </div>

                            <!-- Поиск -->
                            <div class="filter-group">
                                <div class="filter-group-title">
                                    <i class="fas fa-search"></i> Поиск
                                </div>
                                <div class="filter-content">
                                    <input type="text" name="search" class="filter-input" placeholder="Поиск по названию..." value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                            </div>

                            <!-- Категории -->
                            <div class="filter-group">
                                <div class="filter-group-title">
                                    <i class="fas fa-tag"></i> Категория
                                </div>
                                <div class="filter-content">
                                    <div class="filter-options">
                                        <label class="filter-option">
                                            <input type="radio" name="category" value="" <?= empty($filters['category']) ? 'checked' : '' ?>>
                                            <span>Все категории</span>
                                        </label>
                                        <?php foreach ($categories as $category): ?>
                                            <label class="filter-option">
                                                <input type="radio" name="category" value="<?= htmlspecialchars($category) ?>" <?= $filters['category'] == $category ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($category) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Тип замка -->
                            <div class="filter-group">
                                <div class="filter-group-title">
                                    <i class="fas fa-lock"></i> Тип замка
                                </div>
                                <div class="filter-content">
                                    <div class="filter-options">
                                        <label class="filter-option">
                                            <input type="radio" name="type" value="" <?= empty($filters['type']) ? 'checked' : '' ?>>
                                            <span>Все типы</span>
                                        </label>
                                        <label class="filter-option">
                                            <input type="radio" name="type" value="slider" <?= $filters['type'] == 'slider' ? 'checked' : '' ?>>
                                            <span><i class="fas fa-sliders-h"></i> Слайдер (с бегунком)</span>
                                        </label>
                                        <label class="filter-option">
                                            <input type="radio" name="type" value="ziplock" <?= $filters['type'] == 'ziplock' ? 'checked' : '' ?>>
                                            <span><i class="fas fa-lock"></i> ZIP-LOCK (гриппер)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Толщина -->
                            <div class="filter-group">
                                <div class="filter-group-title">
                                    <i class="fas fa-ruler"></i> Толщина (мкм)
                                </div>
                                <div class="filter-content">
                                    <div class="filter-options">
                                        <label class="filter-option">
                                            <input type="radio" name="thickness" value="" <?= empty($filters['thickness']) ? 'checked' : '' ?>>
                                            <span>Любая</span>
                                        </label>
                                        <?php foreach ($thicknesses as $thickness): ?>
                                            <label class="filter-option">
                                                <input type="radio" name="thickness" value="<?= $thickness ?>" <?= $filters['thickness'] == $thickness ? 'checked' : '' ?>>
                                                <span><?= $thickness ?> мкм</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Цвет -->
                            <div class="filter-group">
                                <div class="filter-group-title">
                                    <i class="fas fa-palette"></i> Цвет
                                </div>
                                <div class="filter-content">
                                    <div class="filter-options">
                                        <label class="filter-option">
                                            <input type="radio" name="color" value="" <?= empty($filters['color']) ? 'checked' : '' ?>>
                                            <span>Любой</span>
                                        </label>
                                        <?php foreach ($colors as $color): ?>
                                            <label class="filter-option">
                                                <input type="radio" name="color" value="<?= htmlspecialchars($color) ?>" <?= $filters['color'] == $color ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($color) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Размер -->
                            <div class="filter-group">
                                <div class="filter-group-title">
                                    <i class="fas fa-arrows-alt"></i> Размер (мм)
                                </div>
                                <div class="filter-content">
                                    <div class="filter-row">
                                        <div class="filter-label">Ширина:</div>
                                        <div class="filter-inputs">
                                            <input type="number" name="min_width" class="filter-input small" placeholder="от" value="<?= htmlspecialchars($filters['min_width']) ?>">
                                            <span class="filter-separator">-</span>
                                            <input type="number" name="max_width" class="filter-input small" placeholder="до" value="<?= htmlspecialchars($filters['max_width']) ?>">
                                        </div>
                                    </div>
                                    <div class="filter-row">
                                        <div class="filter-label">Высота:</div>
                                        <div class="filter-inputs">
                                            <input type="number" name="min_height" class="filter-input small" placeholder="от" value="<?= htmlspecialchars($filters['min_height']) ?>">
                                            <span class="filter-separator">-</span>
                                            <input type="number" name="max_height" class="filter-input small" placeholder="до" value="<?= htmlspecialchars($filters['max_height']) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Только в наличии -->
                            <div class="filter-group">
                                <div class="filter-content">
                                    <label class="filter-checkbox">
                                        <input type="checkbox" name="in_stock" value="yes" <?= $filters['in_stock'] === 'yes' ? 'checked' : '' ?>>
                                        <span><i class="fas fa-check-circle"></i> Только в наличии</span>
                                    </label>
                                </div>
                            </div>

                            <button type="button" class="btn btn-primary btn-block mobile-apply-filters">
                                <i class="fas fa-search"></i> Применить фильтры
                            </button>
                        </div>
                    </div>

<!-- Спецпредложения (сетка 4 в ряд) -->
<?php if (!empty($specialOffers)): ?>
    <h2 class="section-title" style="font-size: 1.5rem; margin-bottom: 20px;">
        <i class="fas fa-gift" style="color: #f59e0b;"></i> Специальные предложения
    </h2>
    <div class="compact-grid">
        <?php foreach ($specialOffers as $offer): 
            $price = number_format($offer['price_rub'], 2, ',', ' ');
        ?>
        <div class="compact-card special">
            <div class="compact-badge">🔥 ХИТ</div>
            <div class="compact-image">
                <img src="<?= htmlspecialchars($offer['image_url']) ?>" alt="<?= htmlspecialchars($offer['full_name']) ?>">
            </div>
            <div class="compact-name"><?= htmlspecialchars($offer['short_name'] ?: $offer['full_name']) ?></div>
            <div class="compact-price"><?= $price ?> ₽/шт</div>
            <div class="compact-stock">В наличии: <?= number_format($offer['stock_quantity'], 0, ',', ' ') ?> шт</div>
            <button class="btn btn-primary compact-btn add-to-cart" 
                    data-id="<?= $offer['id'] ?>"
                    data-name="<?= htmlspecialchars($offer['full_name']) ?>"
                    data-price="<?= $offer['price_rub'] ?>">
                <i class="fas fa-shopping-cart"></i> В заявку
            </button>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Популярные товары (сетка 4 в ряд) -->
<?php if (!empty($popularProducts)): ?>
    <h2 class="section-title" style="font-size: 1.5rem; margin: 40px 0 20px;">
        <i class="fas fa-fire" style="color: #f59e0b;"></i> Популярные товары
    </h2>
    <div class="compact-grid">
        <?php foreach ($popularProducts as $product): 
            $price = number_format($product['price_rub'], 2, ',', ' ');
        ?>
        <div class="compact-card">
            <div class="compact-image">
                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['full_name']) ?>">
            </div>
            <div class="compact-name"><?= htmlspecialchars($product['short_name'] ?: $product['full_name']) ?></div>
            <div class="compact-price"><?= $price ?> ₽/шт</div>
            <div class="compact-stock">В наличии: <?= number_format($product['stock_quantity'], 0, ',', ' ') ?> шт</div>
            <button class="btn btn-outline compact-btn add-to-cart" 
                    data-id="<?= $product['id'] ?>"
                    data-name="<?= htmlspecialchars($product['full_name']) ?>"
                    data-price="<?= $product['price_rub'] ?>">
                <i class="fas fa-shopping-cart"></i> В заявку
            </button>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

                    <!-- Сортировка и список товаров -->
                    <h2 class="section-title" style="font-size: 1.5rem; margin: 40px 0 20px;">
                        <i class="fas fa-boxes" style="color: #3498db;"></i> Все товары
                    </h2>
                    
                    <div class="catalog-toolbar">
                        <div class="toolbar-left">
                            <div class="sort-block">
                                <label for="sort">Сортировка:</label>
                                <select id="sort" name="sort" class="sort-select">
                                    <option value="popular" <?= $filters['sort'] == 'popular' ? 'selected' : '' ?>>По популярности</option>
                                    <option value="price_asc" <?= $filters['sort'] == 'price_asc' ? 'selected' : '' ?>>Сначала дешевле</option>
                                    <option value="price_desc" <?= $filters['sort'] == 'price_desc' ? 'selected' : '' ?>>Сначала дороже</option>
                                    <option value="stock" <?= $filters['sort'] == 'stock' ? 'selected' : '' ?>>По наличию</option>
                                </select>
                            </div>
                        </div>
                        <div class="toolbar-right">
                            <div class="view-toggle">
                                <button class="view-btn active" data-view="grid">
                                    <i class="fas fa-th-large"></i>
                                </button>
                                <button class="view-btn" data-view="list">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Сетка товаров -->
                    <?php if (empty($result['products'])): ?>
                        <div class="no-products">
                            <i class="fas fa-box-open"></i>
                            <h3>Товары не найдены</h3>
                            <p>Попробуйте изменить параметры фильтрации</p>
                            <a href="/katalog_zip_paketov" class="btn btn-primary">Сбросить фильтры</a>
                        </div>
                    <?php else: ?>
                        <div class="products-grid" id="productsGrid">
                            <?php foreach ($result['products'] as $product): 
                                $price = number_format($product['price_rub'], 2, ',', ' ');
                            ?>
                            <div class="product-card" data-id="<?= $product['id'] ?>">
                                <div class="product-badges">
                                    <?php if ($product['stock_quantity'] > 100000): ?>
                                        <span class="badge badge-stock">Много</span>
                                    <?php endif; ?>
                                    <?php if ($product['xyz_class'] == 'X'): ?>
                                        <span class="badge badge-popular">Хит</span>
                                    <?php endif; ?>
                                    <?php if ($product['abc_class'] == 'A'): ?>
                                        <span class="badge badge-abc">Топ</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-image">
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($product['full_name']) ?>" 
                                         loading="lazy">
                                    <div class="product-type-icon">
                                        <i class="fas fa-<?= $product['type_icon'] ?>"></i>
                                    </div>
                                </div>
                                
                                <div class="product-info">
                                    <h3 class="product-name">
                                        <a href="/product/<?= $product['id'] ?>">
                                            <?= htmlspecialchars($product['short_name'] ?: $product['full_name']) ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="product-specs">
                                        <div class="spec-item">
                                            <i class="fas fa-ruler"></i>
                                            <span><?= $product['width'] ?>×<?= $product['height'] ?> мм</span>
                                        </div>
                                        <?php if ($product['thickness']): ?>
                                        <div class="spec-item">
                                            <i class="fas fa-layer-group"></i>
                                            <span><?= $product['thickness'] ?> мкм</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($product['color']): ?>
                                        <div class="spec-item">
                                            <i class="fas fa-palette"></i>
                                            <span><?= htmlspecialchars($product['color']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-stock">
                                        <div class="stock-info <?= $product['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                            <i class="fas <?= $product['stock_quantity'] > 0 ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                                            <span><?= $product['stock_quantity'] > 0 ? 'В наличии' : 'Под заказ' ?></span>
                                        </div>
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                        <div class="stock-count"><?= number_format($product['stock_quantity'], 0, ',', ' ') ?> шт</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pack-note" style="color:#94a3b8;font-size:0.8rem;margin:4px 0">
                                        <?= htmlspecialchars(pv_pack_note((int)($product['min_order_qty'] ?? 1), (int)($product['qty_step'] ?? 1))) ?>
                                    </div>

                                    <div class="product-pricing">
                                        <div class="price-block">
                                            <span class="current-price"><?= $price ?> ₽</span>
                                            <span class="price-unit">/шт</span>
                                        </div>
                                        <?php if ($product['stock_quantity'] > 10000): ?>
                                        <div class="price-opt">
                                            <i class="fas fa-tag"></i> Оптовые цены
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-actions">
                                        <button class="btn btn-primary btn-sm add-to-cart" 
                                                data-id="<?= $product['id'] ?>"
                                                data-name="<?= htmlspecialchars($product['full_name']) ?>"
                                                data-price="<?= $product['price_rub'] ?>">
                                            <i class="fas fa-shopping-cart"></i> В заявку
                                        </button>
                                        <button class="btn btn-outline btn-sm1 quick-view" 
                                                data-id="<?= $product['id'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Пагинация -->
                        <?php if ($result['totalPages'] > 1): ?>
                        <div class="pagination">
                            <?php if ($result['page'] > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] - 1])) ?>" class="pagination-prev">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <div class="pagination-pages">
                                <?php
                                $start = max(1, $result['page'] - 2);
                                $end = min($result['totalPages'], $result['page'] + 2);
                                
                                if ($start > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="page-link">1</a>
                                    <?php if ($start > 2): ?>
                                        <span class="page-dots">...</span>
                                    <?php endif; ?>
                                <?php endif;
                                
                                for ($i = $start; $i <= $end; $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="page-link <?= $i == $result['page'] ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor;
                                
                                if ($end < $result['totalPages']): ?>
                                    <?php if ($end < $result['totalPages'] - 1): ?>
                                        <span class="page-dots">...</span>
                                    <?php endif; ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['totalPages']])) ?>" class="page-link">
                                        <?= $result['totalPages'] ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($result['page'] < $result['totalPages']): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $result['page'] + 1])) ?>" class="pagination-next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- SEO-блок с ассортиментом -->
            <section class="seo-section">
                <div class="container">
                    <div class="seo-content">
                        <h2>ZIP-пакеты от производителя ZLOCK</h2>
                        <p>
                            Компания ZLOCK предлагает широкий ассортимент ZIP-пакетов собственного производства. 
                            В нашем каталоге вы найдете пакеты с замком слайдер (с бегунком) и классические ZIP-LOCK пакеты (грипперы) 
                            различных размеров - от миниатюрных 15×20 см до крупных 35×45 см. Все пакеты доступны в различной толщине 
                            от 35 до 100 мкм, а также в прозрачном и матовом исполнении.
                        </p>
                        <p>
                            Мы производим упаковку для самых разных сфер: от фасовки продуктов питания и косметики до упаковки 
                            текстиля и электроники. Благодаря собственному производству, мы можем предложить:
                        </p>
                        <ul>
                            <li>Изготовление пакетов любых нестандартных размеров под заказ</li>
                            <li>Нанесение печати любой сложности (от 1 до 4 цветов)</li>
                            <li>Оптовые цены напрямую от производителя со скидками до 30%</li>
                            <li>Бесплатные образцы для тестирования качества</li>
                            <li>Доставку по всей России транспортными компаниями</li>
                        </ul>
                        <p>
                            Все представленные в каталоге пакеты есть в наличии на складе. Для крупных оптовых заказов 
                            действуют специальные условия - запросите коммерческое предложение через форму обратной связи 
                            или свяжитесь с нами по телефону.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <?php include __DIR__ . '/../footer.php'; ?>
    </div>

    <!-- Модальное окно быстрого просмотра -->
    <div class="modal" id="quickViewModal">
        <div class="modal-content modal-lg">
            <button class="modal-close">
                <i class="fas fa-times"></i>
            </button>
            <div class="quick-view-content" id="quickViewContent">
                <!-- Загружается через AJAX -->
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
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-phone"></i> Заказать звонок
                </button>
            </form>
        </div>
    </div>

    <!-- Форма для применения фильтров -->
    <form id="filterForm" method="GET" style="display: none;">
        <input type="hidden" name="category" id="filterCategory" value="<?= htmlspecialchars($filters['category']) ?>">
        <input type="hidden" name="type" id="filterType" value="<?= htmlspecialchars($filters['type']) ?>">
        <input type="hidden" name="thickness" id="filterThickness" value="<?= htmlspecialchars($filters['thickness']) ?>">
        <input type="hidden" name="color" id="filterColor" value="<?= htmlspecialchars($filters['color']) ?>">
        <input type="hidden" name="min_width" id="filterMinWidth" value="<?= htmlspecialchars($filters['min_width']) ?>">
        <input type="hidden" name="max_width" id="filterMaxWidth" value="<?= htmlspecialchars($filters['max_width']) ?>">
        <input type="hidden" name="min_height" id="filterMinHeight" value="<?= htmlspecialchars($filters['min_height']) ?>">
        <input type="hidden" name="max_height" id="filterMaxHeight" value="<?= htmlspecialchars($filters['max_height']) ?>">
        <input type="hidden" name="in_stock" id="filterInStock" value="<?= htmlspecialchars($filters['in_stock']) ?>">
        <input type="hidden" name="search" id="filterSearch" value="<?= htmlspecialchars($filters['search']) ?>">
        <input type="hidden" name="sort" id="filterSort" value="<?= htmlspecialchars($filters['sort']) ?>">
        <input type="hidden" name="page" id="filterPage" value="1">
    </form>

    <!-- Основные скрипты -->
    <script src="/js/script.js"></script>
    <script src="/js/catalog.js"></script>
    <script src="/js/cart.js"></script>

 <!-- Скрипт для мобильного фильтра -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterToggle = document.getElementById('filterToggle');
        const mobileFilters = document.getElementById('mobileFiltersPanel');
        
        if (filterToggle && mobileFilters) {
            filterToggle.addEventListener('click', function() {
                mobileFilters.classList.toggle('show');
                filterToggle.classList.toggle('active');
            });
        }
        
        // Компактные фильтры для ПК
        const compactApplyBtn = document.getElementById('compactApplyFilters');
        if (compactApplyBtn) {
            compactApplyBtn.addEventListener('click', function() {
                const search = document.getElementById('compactSearch')?.value || '';
                const category = document.getElementById('compactCategory')?.value || '';
                const type = document.getElementById('compactType')?.value || '';
                const thickness = document.getElementById('compactThickness')?.value || '';
                
                document.getElementById('filterSearch').value = search;
                document.getElementById('filterCategory').value = category;
                document.getElementById('filterType').value = type;
                document.getElementById('filterThickness').value = thickness;
                document.getElementById('filterPage').value = '1';
                
                document.getElementById('filterForm').submit();
            });
        }
        
        // Мобильные фильтры - кнопка применения
        const mobileApplyBtn = document.querySelector('.mobile-apply-filters');
        if (mobileApplyBtn) {
            mobileApplyBtn.addEventListener('click', function() {
                const categoryRadio = document.querySelector('input[name="category"]:checked');
                if (categoryRadio) document.getElementById('filterCategory').value = categoryRadio.value;
                
                const typeRadio = document.querySelector('input[name="type"]:checked');
                if (typeRadio) document.getElementById('filterType').value = typeRadio.value;
                
                const thicknessRadio = document.querySelector('input[name="thickness"]:checked');
                if (thicknessRadio) document.getElementById('filterThickness').value = thicknessRadio.value;
                
                const colorRadio = document.querySelector('input[name="color"]:checked');
                if (colorRadio) document.getElementById('filterColor').value = colorRadio.value;
                
                document.getElementById('filterMinWidth').value = document.querySelector('input[name="min_width"]')?.value || '';
                document.getElementById('filterMaxWidth').value = document.querySelector('input[name="max_width"]')?.value || '';
                document.getElementById('filterMinHeight').value = document.querySelector('input[name="min_height"]')?.value || '';
                document.getElementById('filterMaxHeight').value = document.querySelector('input[name="max_height"]')?.value || '';
                
                const inStockCheck = document.querySelector('input[name="in_stock"]');
                document.getElementById('filterInStock').value = inStockCheck && inStockCheck.checked ? 'yes' : '';
                
                document.getElementById('filterSearch').value = document.querySelector('input[name="search"]')?.value || '';
                document.getElementById('filterPage').value = '1';
                document.getElementById('filterForm').submit();
            });
        }
    });
    </script>

    <!-- Скрипты для reCAPTCHA и обратного звонка -->
    <script>
    // Инициализация reCAPTCHA
    grecaptcha.ready(function() {
        console.log('reCAPTCHA готов к работе');
    });

    // Обработчик формы обратного звонка
    window.addEventListener('load', function() {
        const callbackForm = document.getElementById('callbackForm');
        if (callbackForm && !callbackForm.hasAttribute('data-handler-attached')) {
            callbackForm.setAttribute('data-handler-attached', 'true');
            callbackForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const phone = this.querySelector('input[name="phone"]');
                if (!phone || !phone.value.trim()) {
                    alert('Пожалуйста, введите телефон');
                    return;
                }
                
                if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', {action: 'callback'}).then(function(token) {
                            const tokenInput = document.getElementById('callbackRecaptchaToken');
                            if (tokenInput) {
                                tokenInput.value = token;
                            }
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
</body>
</html>