<?php
// Подключаем конфигурацию
require_once 'includes/config.php';

// UTM трекер инициализируется ТОЛЬКО в header.php, убираем дублирование
if (file_exists(__DIR__ . '/includes/utm_tracker.php')) {
    require_once __DIR__ . '/includes/utm_tracker.php';
    // UTMTracker::init(); - ЗАКОММЕНТИРОВАНО, т.к. инициализация в header.php
}
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
    <link rel="icon" type="image/png" sizes="16x16" href="images/zlock.ico">
    <link rel="icon" href="https://zippaket-optom.ru/images/favicon.ico" type="image/x-icon">
    
    <!-- reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Стили -->
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/premium.css">
    <link rel="stylesheet" href="/css/home-premium.css">

    <!-- JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "ZLOCK - Производство zip пакетов оптом",
      "url": "https://zippaket-optom.ru/",
      "logo": "https://zippaket-optom.ru/images/logo.png",
      "description": "Производство ZIP-пакетов на заказ",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "",
        "addressLocality": "Москва",
        "addressCountry": "RU"
      },
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+7 (920) 346-50-67",
        "contactType": "customer service",
        "availableLanguage": "Russian"
      }
    }
    </script>
</head>
<body class="premium">
    <div class="site-wrapper">
        <!-- Header -->
        <?php include 'header.php'; ?>

        <main class="main-content">
            <!-- ===== БЛОК 1: ГЕРОЙ С ВИДЕО ===== -->
            <section class="hero-section">
                <div class="hero-video-bg">
                    <video autoplay muted loop playsinline preload="metadata" class="hero-video">
                        <source src="images/main_zip.mp4" type="video/mp4">
                        <img src="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='800'%20height='600'%20viewBox='0%200%20800%20600'%3E%3Crect%20width='800'%20height='600'%20fill='%232563eb'/%3E%3Ctext%20x='400'%20y='300'%20font-family='Arial'%20font-size='60'%20fill='white'%20text-anchor='middle'%20dominant-baseline='middle'%3EZLOCK%3C/text%3E%3C/svg%3E"
     alt="Производство ZIP-пакетов" 
     class="video-fallback">
                    </video>
                    <div class="video-overlay"></div>
                </div>
                
                <div class="container">
                    <div class="hero-grid">
                        <div class="hero-content">
                            <div class="hero-badge">
                                <i class="fas fa-badge-check"></i>
                                <span>Производитель №1 в России</span>
                            </div>
                            
                            <h1 class="hero-title">
                                Производство <span class="text-gradient">ZIP-пакетов</span><br>
                                на заказ от 1 дня
                            </h1>
                            
                            <p class="hero-description">
                                Получите расчёт стоимости в течение 10 минут, напрямую от производителя со скидкой до 30%.<br><br>
                                Собственное производство, любой тираж, гарантия качества.
                            </p>
                            
                            <div class="hero-actions">
                                <a href="#calculator" class="btn btn-primary btn-lg">
                                    <i class="fas fa-calculator"></i>
                                    Рассчитать стоимость
                                </a>
                                <!--<a href="#catalog" class="btn btn-outline btn-lg">
                                    <i class="fas fa-box-open"></i>
                                    Смотреть каталог
                                </a>-->
                            </div>
                            
                            <div class="hero-features">
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Бесплатные образцы</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Доставка по РФ</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Собственное производство</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Гарантия качества</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Закомментированный блок hero-image 
                        <div class="hero-image">
                            <div class="image-wrapper">
                                <div class="product-showcase">
                                    <div class="product-item">
                                        <div class="product-icon">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                        <div class="product-label">ZIP-Lock</div>
                                    </div>
                                    <div class="product-item">
                                        <div class="product-icon">
                                            <i class="fas fa-box"></i>
                                        </div>
                                        <div class="product-label">Stand-Up</div>
                                    </div>
                                    <div class="product-item">
                                        <div class="product-icon">
                                            <i class="fas fa-wind"></i>
                                        </div>
                                        <div class="product-label">Вакуумный</div>
                                    </div>
                                </div>
                                <div class="floating-badge">
                                    <i class="fas fa-shipping-fast"></i>
                                    <span>Доставка 1-3 дня</span>
                                </div>
                                <div class="floating-badge badge-secondary">
                                    <i class="fas fa-tag"></i>
                                    <span>Цена от 1,5 ₽</span>
                                </div>
                            </div>
                        </div>
                        -->
                    </div>
                </div>
            </section>

            <!-- ===== БЛОК 2: ПРЕИМУЩЕСТВА (ЗАКОММЕНТИРОВАН) ===== -->
            <!--
            <section class="advantages-section" id="advantages">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Почему выбирают нас</h2>
                        <p class="section-subtitle">Мы создаём упаковку, которая работает на ваш успех</p>
                    </div>
                    
                    <div class="advantages-grid">
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-industry"></i>
                            </div>
                            <h3>Собственное производство</h3>
                            <p>Полный контроль качества на всех этапах производства</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <h3>Высокая скорость</h3>
                            <p>Изготовление от 1 дня, срочные заказы — в приоритете</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-paint-brush"></i>
                            </div>
                            <h3>Любой дизайн</h3>
                            <p>Печать в 4 цвета, тиснение, индивидуальная разработка</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                            <h3>Доставка по РФ</h3>
                            <p>Работаем с проверенными транспортными компаниями</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <h3>Персональный менеджер</h3>
                            <p>Сопровождение заказа от расчёта до доставки</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3>Гарантия качества</h3>
                            <p>Используем только сертифицированные материалы</p>
                        </div>
                    </div>
                </div>
            </section>
            -->
            
            <!-- ===== БЛОК 3: СПЕЦИАЛЬНЫЕ ПРЕДЛОЖЕНИЯ ===== -->
            <section class="special-offer-section" id="special-offer">
                <div class="container">
                    <div class="special-offer-header">
                        <div class="offer-badge">
                            <i class="fas fa-tag"></i>
                            <span>Специальная распродажа</span>
                        </div>
                        <h2 class="section-title">Пакеты по специальным ценам на объём</h2>
                        <p class="section-subtitle">Распродажа остатков производства. Ограниченное количество!</p>
                        
                        <div class="offer-timer">
                            <div class="timer-header">
                                <i class="fas fa-clock"></i>
                                <span>Предложение действует до:</span>
                            </div>
                            
                            <div class="timer-display" id="offerTimer">
                                <div class="timer-item">
                                    <span class="timer-value">00</span>
                                    <span class="timer-label">дней</span>
                                </div>
                                <div class="timer-separator">:</div>
                                <div class="timer-item">
                                    <span class="timer-value">00</span>
                                    <span class="timer-label">часов</span>
                                </div>
                                <div class="timer-separator">:</div>
                                <div class="timer-item">
                                    <span class="timer-value">00</span>
                                    <span class="timer-label">минут</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="material-comparison">
                        <div class="material-card eva-material">
                            <div class="material-header">
                                <div class="material-preview">
                                    <div class="product-image-container">
                                        <img src="images/eva.png" 
                                             alt="ZIP-пакет из матового материала EVA" 
                                             class="product-preview-image"
                                             loading="lazy">
                                        <div class="image-overlay">
                                            <span class="overlay-text">Матовый эффект</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="material-icon">
                                    <i class="fas fa-snowflake"></i>
                                </div>
                                <h3>EVA (матовый)</h3>
                                <div class="material-badge premium">Премиум</div>
                            </div>
                            <div class="material-features">
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Мягкий и гибкий</span>
                                </div>
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Устойчив к морозу</span>
                                </div>
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Прочный на разрыв</span>
                                </div>
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Матовый эффект</span>
                                </div>
                            </div>
                            <div class="material-price">
                                <span class="price-label">Стоимость на 300к+ шт:</span>
                                <span class="price-value">от 1.5 ₽/шт</span>
                            </div>
                        </div>
                        
                        <div class="material-divider">
                            <div class="divider-text">VS</div>
                        </div>
                        
                        <div class="material-card pvd-material">
                            <div class="material-header">
                                <div class="material-preview">
                                    <div class="product-image-container">
                                        <img src="/images/pvd.png" 
                                             alt="ZIP-пакет из прозрачного материала ПВД" 
                                             class="product-preview-image"
                                             loading="lazy">
                                        <div class="image-overlay">
                                            <span class="overlay-text">Кристальная прозрачность</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="material-icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <h3>ПВД (прозрачный)</h3>
                                <div class="material-badge popular">Популярный</div>
                            </div>
                            <div class="material-features">
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Кристальная прозрачность</span>
                                </div>
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Блестящая поверхность</span>
                                </div>
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Экономичный вариант</span>
                                </div>
                                <div class="feature1">
                                    <i class="fas fa-check"></i>
                                    <span>Идеальная видимость товара</span>
                                </div>
                            </div>
                            <div class="material-price">
                                <span class="price-label">Стоимость на 300к+ шт:</span>
                                <span class="price-value">от 0.35 ₽/шт</span>
                            </div>
                        </div>
                    </div>

                    <div class="offer-grid">
                        <!-- Слайдеры с бегунком -->
                        <div class="offer-category">
                            <div class="category-header">
                                <h3><i class="fas fa-sliders-h"></i> Пакеты слайдеры с бегунком</h3>
                                <div class="category-badge">Хит продаж</div>
                            </div>
                            
                            <div class="products-grid">
                                <!-- 25*30 см -->
                                <div class="product-offer-card">
                                    <div class="product-image-mini">
                                        <img src="images/begun.png" alt="Слайдер пакет 25×30 см" loading="lazy">
                                    </div>
                                    <div class="product-size">
                                        <span class="size-value">25 × 30 см</span>
                                        <span class="size-thickness">60 мкм</span>
                                    </div>
                                    <div class="product-prices">
                                        <div class="price-row">
                                            <span class="price-label">Опт от 300к:</span>
                                            <span class="price-value">3.5 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Опт от 20к:</span>
                                            <span class="price-value">3.99 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Розница от 3к:</span>
                                            <span class="price-value">4.5 ₽/шт</span>
                                        </div>
                                    </div>
                                    <div class="product-stock">
                                        <div class="stock-info">
                                            <i class="fas fa-box"></i>
                                            <span>В наличии: 1,100,000 шт</span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-fill" style="width: 85%"></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-block add-to-cart" data-product-id="slider-25-30" data-size="25*30" data-type="slider">
                                        <i class="fas fa-shopping-cart"></i>
                                        Добавить в заявку
                                    </button>
                                </div>

                                <!-- 30*35 см -->
                                <div class="product-offer-card">
                                    <div class="product-image-mini">
                                        <img src="images/begun.png" alt="Слайдер пакет 30×35 см" loading="lazy">
                                    </div>
                                    <div class="product-size">
                                        <span class="size-value">30 × 35 см</span>
                                        <span class="size-thickness">60 мкм</span>
                                    </div>
                                    <div class="product-prices">
                                        <div class="price-row">
                                            <span class="price-label">Опт от 300к:</span>
                                            <span class="price-value">4.9 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Опт от 20к:</span>
                                            <span class="price-value">5.8 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Розница от 3к:</span>
                                            <span class="price-value">6.5 ₽/шт</span>
                                        </div>
                                    </div>
                                    <div class="product-stock">
                                        <div class="stock-info">
                                            <i class="fas fa-box"></i>
                                            <span>В наличии: 720,000 шт</span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-fill" style="width: 72%"></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-block add-to-cart" data-product-id="slider-30-35" data-size="30*35" data-type="slider">
                                        <i class="fas fa-shopping-cart"></i>
                                        Добавить в заявку
                                    </button>
                                </div>

                                <!-- 35*45 см -->
                                <div class="product-offer-card">
                                    <div class="product-image-mini">
                                        <img src="images/begun.png" alt="Слайдер пакет 35×45 см" loading="lazy">
                                    </div>
                                    <div class="product-size">
                                        <span class="size-value">35 × 45 см</span>
                                        <span class="size-thickness">60 мкм</span>
                                    </div>
                                    <div class="product-prices">
                                        <div class="price-row">
                                            <span class="price-label">Опт от 300к:</span>
                                            <span class="price-value">6.9 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Опт от 20к:</span>
                                            <span class="price-value">7.5 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Розница от 3к:</span>
                                            <span class="price-value">8.5 ₽/шт</span>
                                        </div>
                                    </div>
                                    <div class="product-stock">
                                        <div class="stock-info">
                                            <i class="fas fa-box"></i>
                                            <span>В наличии: 200,000 шт</span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-fill" style="width: 45%"></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-block add-to-cart" data-product-id="slider-35-45" data-size="35*45" data-type="slider">
                                        <i class="fas fa-shopping-cart"></i>
                                        Добавить в заявку
                                    </button>
                                </div>
                            </div>

                            <div class="custom-size-request">
                                <div class="request-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="request-content">
                                    <h4>Не нашли подходящий размер?</h4>
                                    <p>Мы можем изготовить слайдер-пакеты любого размера и толщины под ваши задачи</p>
                                </div>
                                <a href="#calculator" class="btn btn-outline">
                                    <i class="fas fa-calculator"></i>
                                    Рассчитай свой
                                </a>
                            </div>
                        </div>

                        <!-- ZIP-LOCK пакеты -->
                        <div class="offer-category">
                            <div class="category-header">
                                <h3><i class="fas fa-lock"></i> Пакеты с замком ZIP-LOCK (грипперы)</h3>
                                <div class="category-badge" style="background: #10b981;">Большой выбор</div>
                            </div>
                            
                            <div class="products-grid">
                                <!-- 4*6 см -->
                                <div class="product-offer-card">
                                    <div class="product-image-mini">
                                        <img src="images/zip.png" alt="ZIP-Lock пакет 4×6 см" loading="lazy">
                                    </div>
                                    <div class="product-size">
                                        <span class="size-value">4 × 6 см</span>
                                        <span class="size-thickness">50 мкм</span>
                                    </div>
                                    <div class="product-prices">
                                        <div class="price-row">
                                            <span class="price-label">Опт от 300к:</span>
                                            <span class="price-value">0.26 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Опт от 20к:</span>
                                            <span class="price-value">0.29 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Розница от 3к:</span>
                                            <span class="price-value">0.35 ₽/шт</span>
                                        </div>
                                    </div>
                                    <div class="product-stock">
                                        <div class="stock-info">
                                            <i class="fas fa-box"></i>
                                            <span>В наличии: 4,000,000 шт</span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-fill" style="width: 95%"></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-block add-to-cart" data-product-id="ziplock-4-6" data-size="4*6" data-type="ziplock">
                                        <i class="fas fa-shopping-cart"></i>
                                        Добавить в заявку
                                    </button>
                                </div>

                                <!-- 10*15 см -->
                                <div class="product-offer-card">
                                    <div class="product-image-mini">
                                        <img src="images/zip.png" alt="ZIP-Lock пакет 10×15 см" loading="lazy">
                                    </div>
                                    <div class="product-size">
                                        <span class="size-value">10 × 15 см</span>
                                        <span class="size-thickness">50 мкм</span>
                                    </div>
                                    <div class="product-prices">
                                        <div class="price-row">
                                            <span class="price-label">Опт от 300к:</span>
                                            <span class="price-value">0.85 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Опт от 20к:</span>
                                            <span class="price-value">0.95 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Розница от 3к:</span>
                                            <span class="price-value">1.2 ₽/шт</span>
                                        </div>
                                    </div>
                                    <div class="product-stock">
                                        <div class="stock-info">
                                            <i class="fas fa-box"></i>
                                            <span>В наличии: 1,000,000 шт</span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-fill" style="width: 65%"></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-block add-to-cart" data-product-id="ziplock-10-15" data-size="10*15" data-type="ziplock">
                                        <i class="fas fa-shopping-cart"></i>
                                        Добавить в заявку
                                    </button>
                                </div>

                                <!-- 25*30 см -->
                                <div class="product-offer-card">
                                    <div class="product-image-mini">
                                        <img src="images/zip.png" alt="ZIP-Lock пакет 25×30 см" loading="lazy">
                                    </div>
                                    <div class="product-size">
                                        <span class="size-value">25 × 30 см</span>
                                        <span class="size-thickness">60 мкм</span>
                                    </div>
                                    <div class="product-prices">
                                        <div class="price-row">
                                            <span class="price-label">Опт от 300к:</span>
                                            <span class="price-value">2.59 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Опт от 20к:</span>
                                            <span class="price-value">2.99 ₽/шт</span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Розница от 3к:</span>
                                            <span class="price-value">3.9 ₽/шт</span>
                                        </div>
                                    </div>
                                    <div class="product-stock">
                                        <div class="stock-info">
                                            <i class="fas fa-box"></i>
                                            <span>В наличии: 230,000 шт</span>
                                        </div>
                                        <div class="stock-bar">
                                            <div class="stock-fill" style="width: 40%"></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-block add-to-cart" data-product-id="ziplock-25-30" data-size="25*30" data-type="ziplock">
                                        <i class="fas fa-shopping-cart"></i>
                                        Добавить в заявку
                                    </button>
                                </div>
                            </div>

                            <div class="custom-size-request">
                                <div class="request-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="request-content">
                                    <h4>Не нашли подходящий размер?</h4>
                                    <p>Произведём ZIP-LOCK пакеты по вашим индивидуальным параметрам</p>
                                </div>
                                <a href="#calculator" class="btn btn-outline">
                                    <i class="fas fa-calculator"></i>
                                    Рассчитай свой
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="offer-cta">
                        <div class="cta-content">
                            <h3>Хотите заказать больший объём?</h3>
                            <p>Получите индивидуальную скидку при заказе от 150,000 рублей. Специальные условия для крупных оптовиков!</p>
                            
                            <div class="cta-benefits">
                                <div class="benefit">
                                    <i class="fas fa-percentage"></i>
                                    <span>Дополнительная скидка до 30%</span>
                                </div>
                                <div class="benefit">
                                    <i class="fas fa-truck"></i>
                                    <span>Бесплатная доставка от 1 млн. шт</span>
                                </div>
                                <div class="benefit">
                                    <i class="fas fa-gift"></i>
                                    <span>Предоставляем бесплатные образцы.<br>Чтобы вы смогли убедится в качестве наших zip пакетов!</span>
                                </div>
                            </div>
                            
                            <div class="cta-actions">
                                <a href="#contact" class="btn btn-primary btn-lg">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    Запросить коммерческое предложение
                                </a>
                                <a href="tel:+79203465067" class="btn btn-outline btn-lg">
                                    <i class="fas fa-phone"></i>
                                    Позвонить для консультации
                                </a>
                            </div>
                            
                            <div class="offer-note">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Цены действительны до 28 февраля 2026 года. Количество товара ограничено!</span>
                            </div>

                            <!-- Блок с Telegram ботом -->
                            <div class="telegram-offer">
                                <div class="telegram-divider">
                                    <span>Или</span>
                                </div>
                                
                                <div class="telegram-content">
                                    <div class="telegram-icon">
                                        <i class="fab fa-telegram-plane"></i>
                                    </div>
                                    
                                    <div class="telegram-info">
                                        <h4>Мгновенный расчёт через Telegram бота</h4>
                                        <p>Быстрее, чем через менеджера! Просто запустите бота и получите стоимость за 10 минут</p>
                                        
                                        <div class="telegram-features">
                                            <div class="telegram-feature">
                                                <i class="fas fa-bolt"></i>
                                                <span>Быстрый ответ 24/7</span>
                                            </div>
                                            <div class="telegram-feature">
                                                <i class="fas fa-calculator"></i>
                                                <span>Автоматический расчёт</span>
                                            </div>
                                            <div class="telegram-feature">
                                                <i class="fas fa-file-invoice"></i>
                                                <span>КП в один клик</span>
                                            </div>
                                        </div>
                                        
                                        <a href="https://t.me/zlock_sales_bot" 
                                           class="btn btn-telegram" 
                                           target="_blank" 
                                           rel="noopener noreferrer">
                                            <i class="fab fa-telegram"></i>
                                            Запустить бота @zlock_sales_bot
                                            <span class="btn-badge">быстро</span>
                                        </a>
                                        
                                        <div class="telegram-note">
                                            <i class="fas fa-shield-alt"></i>
                                            <span>Никакой лишней информации — просто цена за 10 минут</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <script src="/js/script.js"></script>

            <!-- ===== БЛОК 4: КАЛЬКУЛЯТОР ===== -->
            <section class="calculator-section" id="calculator">
                <div class="container">
                    <?php
                    require_once 'includes/calculator.php';
                    echo displayCalculatorForm();
                    ?>
                </div>
            </section>

            <!-- ===== БЛОК 5: КАТАЛОГ (ЗАКОММЕНТИРОВАН) ===== -->
            <!--
            <section class="catalog-section" id="catalog">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Наша продукция</h2>
                        <p class="section-subtitle">Широкий выбор ZIP-пакетов для любых задач</p>
                    </div>
                    
                    <div class="catalog-grid">
                        <div class="product-card">
                            <div class="product-image">
                                <img src="/images/products/zip-lock.jpg" alt="ZIP-Lock пакеты" loading="lazy">
                            </div>
                            <div class="product-content">
                                <h3>ZIP-Lock пакеты</h3>
                                <p>Герметичные пакеты с застёжкой zip-lock для хранения продуктов</p>
                                <ul class="product-features">
                                    <li><i class="fas fa-check"></i> Размеры: 10x15 до 30x40 см</li>
                                    <li><i class="fas fa-check"></i> Толщина: 40-120 мкм</li>
                                    <li><i class="fas fa-check"></i> Цветная печать</li>
                                </ul>
                                <div class="product-price">от 1,5 ₽/шт</div>
                                <button class="btn btn-primary btn-block">
                                    <i class="fas fa-shopping-cart"></i>
                                    Заказать
                                </button>
                            </div>
                        </div>
                        
                        <div class="product-card">
                            <div class="product-image">
                                <img src="/images/products/stand-up.jpg" alt="Stand-Up пакеты" loading="lazy">
                            </div>
                            <div class="product-content">
                                <h3>Stand-Up пакеты</h3>
                                <p>Пакеты с дном-стойкой для розничной торговли</p>
                                <ul class="product-features">
                                    <li><i class="fas fa-check"></i> Размеры: 15x20 до 25x35 см</li>
                                    <li><i class="fas fa-check"></i> Толщина: 50-100 мкм</li>
                                    <li><i class="fas fa-check"></i> Ламинация, тиснение</li>
                                </ul>
                                <div class="product-price">от 2,5 ₽/шт</div>
                                <button class="btn btn-primary btn-block">
                                    <i class="fas fa-shopping-cart"></i>
                                    Заказать
                                </button>
                            </div>
                        </div>
                        
                        <div class="product-card">
                            <div class="product-image">
                                <img src="/images/products/vacuum.jpg" alt="Вакуумные пакеты" loading="lazy">
                            </div>
                            <div class="product-content">
                                <h3>Вакуумные пакеты</h3>
                                <p>Пакеты для вакуумной упаковки продуктов</p>
                                <ul class="product-features">
                                    <li><i class="fas fa-check"></i> Размеры: 15x20 до 30x40 см</li>
                                    <li><i class="fas fa-check"></i> Толщина: 80-150 мкм</li>
                                    <li><i class="fas fa-check"></i> Барьерные слои</li>
                                </ul>
                                <div class="product-price">от 3 ₽/шт</div>
                                <button class="btn btn-primary btn-block">
                                    <i class="fas fa-shopping-cart"></i>
                                    Заказать
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            -->

            <!-- ===== БЛОК 6: ПРОИЗВОДСТВО ===== -->
            <section class="production-section" id="production">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Наше производство</h2>
                        <p class="section-subtitle">Современное оборудование и контроль качества</p>
                    </div>
                    
                    <div class="production-steps">
                        <div class="step">
                            <div class="step-number">01</div>
                            <div class="step-content">
                                <h3>Консультация</h3>
                                <p>Обсуждаем требования, подбираем материалы, рассчитываем стоимость</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">02</div>
                            <div class="step-content">
                                <h3>Дизайн</h3>
                                <p>Создаём макет, утверждаем с клиентом, готовим к печати</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">03</div>
                            <div class="step-content">
                                <h3>Печать</h3>
                                <p>Флексопечать на современном оборудовании</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">04</div>
                            <div class="step-content">
                                <h3>Вырубка</h3>
                                <p>Точная вырубка по форме на автоматических станках</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">05</div>
                            <div class="step-content">
                                <h3>Упаковка</h3>
                                <p>Контроль качества, упаковка и подготовка к отправке</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">06</div>
                            <div class="step-content">
                                <h3>Доставка</h3>
                                <p>Отправляем заказ транспортной компанией или курьером</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- ===== БЛОК 7: CTA ===== -->
            <section class="cta-section">
                <div class="container">
                    <div class="cta-content">
                        <h2>Готовы заказать ZIP-пакеты?</h2>
                        <p>Свяжитесь с нами сегодня и получите индивидуальное предложение</p>
                        <div class="cta-actions">
                            <a href="tel:+79203465067" class="btn btn-primary btn-lg">
                                <i class="fas fa-phone"></i>
                                Позвонить сейчас
                            </a>
                            <a href="#contact" class="btn btn-outline btn-lg">
                                <i class="fas fa-envelope"></i>
                                Написать сообщение
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== БЛОК 8: КОНТАКТЫ И ФОРМА ===== -->
            <section class="contact-section" id="contact">
                <div class="container">
                    <div class="contact-grid">
                        <div class="contact-form">
                            <h2>Оставить заявку</h2>
                            <p>Отправьте заявку и получите коммерческое предложение в течение часа</p>
                            
                            <form id="leadForm" class="lead-form">
                                <div class="form-group">
                                    <input type="text" name="name" placeholder="Ваше имя *" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <input type="tel" name="phone" placeholder="Телефон *" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="email" name="email" placeholder="Email">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <textarea name="message" placeholder="Опишите ваш заказ (размер, количество, особенности)..." rows="4"></textarea>
                                </div>
                                
                                <input type="hidden" id="recaptchaToken" name="recaptcha_token">
                                
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i>
                                    Отправить заявку
                                </button>
                                
                                <p class="form-note">Нажимая кнопку, вы соглашаетесь с политикой конфиденциальности</p>
                            </form>
                        </div>
                        
                        <div class="contact-info">
                            <h2>Контакты</h2>
                            
                            <div class="contact-items">
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="contact-details">
                                        <h4>Телефон</h4>
                                        <a href="tel:+79203465067">+7 (920) 346-50-67</a>
                                    </div>
                                </div>
                                
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-details">
                                        <h4>Email</h4>
                                        <a href="mailto:ZTR37@Bk.ru">ZTR37@Bk.ru</a>
                                        <p>Ответим в течение 30 минут</p>
                                    </div>
                                </div>
                                
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="contact-details">
                                        <h4>Время работы</h4>
                                        <p>Пн-Пт: 9:00-18:00</p>
                                        <p>Сб-Вс: выходной</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="social-links">
                                <a href="https://t.me/zlock_sales_bot" class="social-link">
                                    <i class="fab fa-telegram"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== БЛОК 9: SEO-БЛОК С АССОРТИМЕНТОМ ===== -->
            <div class="seo-products-section" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 4rem 0; margin-top: 4rem;">
                <div class="container">
                    <div style="text-align: center; margin-bottom: 3rem;">
                        <h2 style="color: var(--dark); font-size: 2rem; margin-bottom: 1rem;">
                            <i class="fas fa-boxes" style="color: var(--primary); margin-right: 10px;"></i>
                            Ассортимент ZIP-пакетов от производителя
                        </h2>
                        <p style="color: var(--gray-600); font-size: 1.125rem; max-width: 800px; margin: 0 auto;">
                            Производство и продажа различных типов упаковочных пакетов оптом и в розницу
                        </p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2.5rem;">
                        <!-- Колонка 1: Пакеты ПВД слайдеры -->
                        <div class="seo-category-card" style="background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow); border-top: 4px solid #3b82f6;">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-sliders-h" style="color: white; font-size: 1.5rem;"></i>
                                </div>
                                <h3 style="color: var(--dark); margin: 0; font-size: 1.5rem;">
                                    ПАКЕТЫ ПВД слайдеры с бегунком
                                </h3>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="20" data-height="15" data-material="PVD" data-thickness="60">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 20×15 см, матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="20" data-height="25" data-material="PVD" data-thickness="60" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 20×25 см (60 мкм), матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="20" data-height="25" data-material="PVD" data-thickness="60" data-clear="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 20×25 см (60 мкм), прозрачный
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="20" data-height="25" data-material="PVD" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 20×25 см, матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="20" data-height="30" data-material="PVD" data-thickness="60" data-clear="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 20×30 см (60 мкм), прозрачный
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="25" data-height="30" data-material="PVD" data-thickness="60" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 25×30 см (60 мкм), матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="25" data-height="30" data-material="PVD" data-thickness="60" data-clear="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 25×30 см (60 мкм), прозрачный
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="25" data-height="30" data-material="PVD" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 25×30 см, матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="25" data-height="35" data-material="PVD" data-thickness="60" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 25×35 см (60 мкм), матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="30" data-height="30" data-material="PVD" data-thickness="60" data-clear="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 30×30 см (60 мкм) прозрачный
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="30" data-height="35" data-material="PVD" data-thickness="60" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 30×35 см (60 мкм), матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="30" data-height="35" data-material="PVD" data-thickness="60" data-clear="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 30×35 см (60 мкм), прозрачный
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="30" data-height="35" data-material="PVD" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 30×35 см, матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="30" data-height="40" data-material="PVD" data-thickness="60" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 30×40 см (60 мкм), матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="30" data-height="40" data-material="PVD" data-thickness="60" data-clear="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 30×40 см (60 мкм), прозрачный
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="35" data-height="40" data-material="PVD" data-thickness="60" data-clear="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 35×40 см (60 мкм), прозрачный
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="35" data-height="45" data-material="PVD" data-thickness="60" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 35×45 см (60 мкм), матовый
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="slider" data-width="15" data-height="20" data-material="PVD" data-matte="true">
                                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 10px;"></i>
                                    Пакет ПВД с замком слайдер 15×20 см, матовый
                                </a>
                            </div>
                            
                            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed #e5e7eb;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; color: #3b82f6; font-weight: 600;">
                                    <i class="fas fa-industry"></i>
                                    <span>Собственное производство всех размеров</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Колонка 2: Пакеты с замком ZIP-LOCK -->
                        <div class="seo-category-card" style="background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow); border-top: 4px solid #10b981;">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981 0%, #047857 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-lock" style="color: white; font-size: 1.5rem;"></i>
                                </div>
                                <h3 style="color: var(--dark); margin: 0; font-size: 1.5rem;">
                                    Пакеты с замком ZIP-LOCK
                                </h3>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="100" data-thickness="80">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×100 (80 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="150" data-thickness="35">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×150 (35 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="150" data-thickness="60">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×150 (60 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="150" data-thickness="80">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×150 (80 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="150" data-height="200" data-thickness="60">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 150×200 (60 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="200" data-height="200">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 200×200
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="200" data-height="250" data-thickness="35">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 200×250 (35 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="40" data-height="60">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 40×60
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="40" data-height="60" data-thickness="80">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 40×60 (80 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="60" data-height="80" data-thickness="40" data-special="без полосы">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 60×80 (40 мкм) БЕЗ ПОЛОСЫ
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="70" data-height="100" data-thickness="100">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 70×100 (100 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="70" data-height="90" data-thickness="70">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 70×90 (70 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="80" data-height="120">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 80×120
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="80" data-height="120" data-thickness="100">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 80×120 (100 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="80" data-height="120" data-thickness="80">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 80×120 (80 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="120">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×120
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="120" data-height="180">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 120×180
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="150" data-height="200">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 150×200
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="35" data-height="45">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 35×45
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="50" data-height="70">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 50×70
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="60" data-height="80">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 60×80
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="70" data-height="100">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 70×100
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="150">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×150
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="150" data-thickness="100">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×150 (100 мкм)
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="100" data-height="200">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 100×200
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="120" data-height="170">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 120×170
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="150" data-height="220">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 150×220
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="200" data-height="250">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 200×250
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="250" data-height="350">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 250×350
                                </a>
                                <a href="#calculator" class="seo-product-link" data-type="ziplock" data-width="50" data-height="70" data-thickness="40">
                                    <i class="fas fa-check" style="color: #3b82f6; margin-right: 10px;"></i>
                                    Пакет с замком zip-lock 50×70 (40 мкм)
                                </a>
                            </div>
                            
                            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed #e5e7eb;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; color: #10b981; font-weight: 600;">
                                    <i class="fas fa-layer-group"></i>
                                    <span>Любая толщина от 35 до 100 мкм</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SEO текстовый блок -->
                    <div style="margin-top: 4rem; padding: 3rem; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); text-align: left;">
                        <h3 style="color: var(--dark); font-size: 1.75rem; margin-bottom: 1.5rem;">
                            <i class="fas fa-search" style="color: var(--primary); margin-right: 10px;"></i>
                            Производство ZIP-пакетов оптом
                        </h3>
                        
                        <div style="margin-top: 2.5rem; padding-top: 2rem; border-top: 2px solid #f1f5f9;">
                            <p style="color: var(--gray-700); font-size: 1.125rem; max-width: 900px; margin: 0px 45px; line-height: 1.7; text-align:left;">
                                Компания ZLOCK специализируется на производстве и оптовой продаже упаковочных пакетов 
                                с замком типа слайдер и zip-lock. В нашем ассортименте представлены пакеты различных размеров: 
                                от небольших 15×20 см до крупных 35×45 см, с толщиной от 35 до 100 мкм. 
                                Мы производим как прозрачные, так и матовые пакеты ПВД, обеспечивая герметичность 
                                и долговечность упаковки. Работаем с розничными и оптовыми клиентами, 
                                предоставляя индивидуальные условия сотрудничества.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Ключевые слова для SEO -->
                    <div style="margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center;">
                        <span style="padding: 0.5rem 1rem; background: #e0f2fe; color: #0369a1; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> zip пакеты оптом
                        </span>
                        <span style="padding: 0.5rem 1rem; background: #dcfce7; color: #166534; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> пакеты с замком слайдер
                        </span>
                        <span style="padding: 0.5rem 1rem; background: #fef3c7; color: #92400e; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> пвд пакеты производство
                        </span>
                        <span style="padding: 0.5rem 1rem; background: #ede9fe; color: #5b21b6; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> zip-lock пакеты москва
                        </span>
                        <span style="padding: 0.5rem 1rem; background: #fce7f3; color: #9d174d; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> упаковочные пакеты оптом
                        </span>
                    </div>
                </div>
            </div>
        </main>

        <!-- Блок с политикой cookie (фиксированный внизу экрана) -->
        <div id="cookieConsent" class="cookie-consent" style="display: none;">
            <div class="container">
                <div class="cookie-content">
                    <div class="cookie-text">
                        <i class="fas fa-cookie-bite"></i>
                        <p>Мы используем файлы cookie для улучшения работы сайта и предоставления вам наилучшего сервиса. 
                           Продолжая использовать сайт, вы соглашаетесь с 
                           <a href="polconf.html" target="_blank">Политикой конфиденциальности</a> и 
                           <a href="/cookie-policy.html" target="_blank">Политикой использования cookie</a>.
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
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-phone"></i> Заказать звонок
                    </button>
                </form>
            </div>
        </div>

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
            
            // Проверяем, было ли уже принято соглашение
            if (!localStorage.getItem('cookiesAccepted')) {
                setTimeout(() => {
                    if (cookieConsent) {
                        cookieConsent.style.display = 'block';
                        setTimeout(() => {
                            cookieConsent.classList.add('show');
                        }, 100);
                    }
                }, 1500);
            } else {
                if (cookieConsent) {
                    cookieConsent.style.display = 'none';
                }
            }
            
            // Принять все cookies
            if (acceptCookiesBtn) {
                acceptCookiesBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    localStorage.setItem('cookiesAccepted', 'all');
                    localStorage.setItem('analyticsCookies', 'true');
                    localStorage.setItem('marketingCookies', 'true');
                    
                    if (cookieConsent) {
                        cookieConsent.classList.remove('show');
                        setTimeout(() => {
                            cookieConsent.style.display = 'none';
                        }, 400);
                    }
                    
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                });
            }
            
            // Отклонить все
            if (rejectCookiesBtn) {
                rejectCookiesBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    localStorage.setItem('cookiesAccepted', 'none');
                    localStorage.setItem('analyticsCookies', 'false');
                    localStorage.setItem('marketingCookies', 'false');
                    
                    if (cookieConsent) {
                        cookieConsent.classList.remove('show');
                        setTimeout(() => {
                            cookieConsent.style.display = 'none';
                        }, 400);
                    }
                });
            }
            
            // Показать/скрыть настройки
            if (customizeCookiesBtn) {
                customizeCookiesBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (cookieSettings) {
                        cookieSettings.style.display = cookieSettings.style.display === 'none' || cookieSettings.style.display === '' ? 'block' : 'none';
                    }
                });
            }
            
            // Сохранить настройки
            if (saveCookieSettingsBtn) {
                saveCookieSettingsBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const analytics = document.getElementById('analyticsCookies')?.checked || false;
                    const marketing = document.getElementById('marketingCookies')?.checked || false;
                    
                    localStorage.setItem('cookiesAccepted', 'custom');
                    localStorage.setItem('analyticsCookies', analytics);
                    localStorage.setItem('marketingCookies', marketing);
                    
                    if (cookieSettings) {
                        cookieSettings.style.display = 'none';
                    }
                    
                    if (cookieConsent) {
                        cookieConsent.classList.remove('show');
                        setTimeout(() => {
                            cookieConsent.style.display = 'none';
                        }, 400);
                    }
                });
            }
            
            // Восстановить настройки при загрузке
            const savedAnalytics = localStorage.getItem('analyticsCookies');
            const savedMarketing = localStorage.getItem('marketingCookies');
            
            if (savedAnalytics !== null) {
                const analyticsCheckbox = document.getElementById('analyticsCookies');
                if (analyticsCheckbox) {
                    analyticsCheckbox.checked = savedAnalytics === 'true';
                }
            }
            if (savedMarketing !== null) {
                const marketingCheckbox = document.getElementById('marketingCookies');
                if (marketingCheckbox) {
                    marketingCheckbox.checked = savedMarketing === 'true';
                }
            }
        });

        // Обработчики для reCAPTCHA
        window.addEventListener('load', function() {
            const leadForm = document.getElementById('leadForm');
            if (leadForm && !leadForm.hasAttribute('data-handler-attached')) {
                leadForm.setAttribute('data-handler-attached', 'true');
                leadForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const phone = this.querySelector('input[name="phone"]');
                    const name = this.querySelector('input[name="name"]');
                    
                    if (!phone || !phone.value.trim()) {
                        alert('Пожалуйста, введите телефон');
                        return;
                    }
                    
                    if (!name || !name.value.trim()) {
                        alert('Пожалуйста, введите имя');
                        return;
                    }
                    
                    if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                        grecaptcha.ready(function() {
                            grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', {action: 'submit'}).then(function(token) {
                                const tokenInput = document.getElementById('recaptchaToken');
                                if (tokenInput) {
                                    tokenInput.value = token;
                                }
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



    <script>
    // Скрипт для автозаполнения калькулятора
    document.addEventListener('DOMContentLoaded', function() {
        const productLinks = document.querySelectorAll('.seo-product-link');
        
        productLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const type = this.getAttribute('data-type');
                const width = this.getAttribute('data-width');
                const height = this.getAttribute('data-height');
                const thickness = this.getAttribute('data-thickness');
                const material = this.getAttribute('data-material');
                const isMatte = this.getAttribute('data-matte');
                const isClear = this.getAttribute('data-clear');
                const special = this.getAttribute('data-special');
                
                const calculatorSection = document.getElementById('calculator');
                if (calculatorSection) {
                    calculatorSection.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
                
                setTimeout(() => {
                    fillCalculatorForm(type, width, height, thickness, material, isMatte, isClear, special);
                }, 500);
            });
        });
        
        function fillCalculatorForm(type, width, height, thickness, material, isMatte, isClear, special) {
            const typeSelect = document.getElementById('type');
            const widthInput = document.getElementById('width');
            const heightInput = document.getElementById('height');
            const thicknessSelect = document.getElementById('thickness');
            const materialSelect = document.getElementById('material');
            
            if (typeSelect && type) {
                for (let option of typeSelect.options) {
                    if (option.value === type || option.text.toLowerCase().includes(type)) {
                        typeSelect.value = option.value;
                        break;
                    }
                }
                typeSelect.dispatchEvent(new Event('change'));
            }
            
            if (widthInput && width) {
                widthInput.value = width;
                widthInput.dispatchEvent(new Event('input'));
            }
            
            if (heightInput && height) {
                heightInput.value = height;
                heightInput.dispatchEvent(new Event('input'));
            }
            
            if (thicknessSelect && thickness) {
                for (let option of thicknessSelect.options) {
                    if (parseInt(option.value) === parseInt(thickness)) {
                        thicknessSelect.value = option.value;
                        break;
                    }
                }
                thicknessSelect.dispatchEvent(new Event('change'));
            }
            
            if (materialSelect && material) {
                let selectedMaterial = 'PVD';
                
                if (isMatte) {
                    selectedMaterial = 'EVA';
                } else if (material === 'PVD') {
                    selectedMaterial = 'PVD';
                }
                
                for (let option of materialSelect.options) {
                    if (option.value === selectedMaterial || option.text.toLowerCase().includes(selectedMaterial.toLowerCase())) {
                        materialSelect.value = option.value;
                        break;
                    }
                }
                materialSelect.dispatchEvent(new Event('change'));
            }
            
            showNotification(`Калькулятор заполнен параметрами: ${width}×${height} см${thickness ? ', ' + thickness + ' мкм' : ''}${material ? ', ' + material : ''}`);
        }
        
        function showNotification(message) {
            let notificationContainer = document.getElementById('seo-notification-container');
            
            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.id = 'seo-notification-container';
                notificationContainer.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    max-width: 350px;
                `;
                document.body.appendChild(notificationContainer);
            }
            
            const notification = document.createElement('div');
            notification.style.cssText = `
                background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                color: white;
                padding: 15px 20px;
                border-radius: 10px;
                margin-bottom: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            notification.innerHTML = `
                <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
                <div>
                    <strong>Параметры загружены!</strong>
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-top: 3px;">${message}</div>
                </div>
            `;
            
            notificationContainer.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        if (!document.getElementById('seo-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'seo-notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
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