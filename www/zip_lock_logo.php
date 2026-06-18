<?php
// Подключаем конфигурацию
require_once 'includes/config.php';

// UTM трекер инициализируется ТОЛЬКО в header.php
if (file_exists(__DIR__ . '/includes/utm_tracker.php')) {
    require_once __DIR__ . '/includes/utm_tracker.php';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Зип лок пакеты с логотипом | Производство ZIP-LOCK пакетов с печатью</title>
    <meta name="description" content="Производство ZIP-LOCK пакетов с логотипом на заказ. Собственное производство, печать любого тиража, доставка по РФ. Бесплатные образцы и расчёт стоимости онлайн.">
    
    <!-- Open Graph -->
    <meta property="og:title" content="Зип лок пакеты с логотипом | Завод по производству ZIP-LOCK пакетов">
    <meta property="og:description" content="Производство ZIP-LOCK пакетов с логотипом на заказ. Собственное производство, быстрые сроки, гарантия качества.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zippaket-optom.ru/zip-lock-pakety-s-logotipom">
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
    <link rel="stylesheet" href="/css/shop-dark.css">

    <!-- JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "ZLOCK - Производство ZIP-LOCK пакетов с логотипом",
      "url": "https://zippaket-optom.ru/zip-lock-pakety-s-logotipom",
      "logo": "https://zippaket-optom.ru/images/logo.png",
      "description": "Производство ZIP-LOCK пакетов с логотипом на заказ",
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
<body class="zlock">
    <div class="site-wrapper">
        <!-- Header -->
        <?php include 'header.php'; ?>

        <main class="main-content">
            <!-- ===== БЛОК 1: ГЕРОЙ ДЛЯ ZIP-LOCK С ЛОГОТИПОМ ===== -->
            <section class="hero-section">
                <div class="hero-video-bg">
                    <video autoplay muted loop playsinline preload="metadata" class="hero-video">
                        <source src="images/main_zip.mp4" type="video/mp4">
                        <img src="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='800'%20height='600'%20viewBox='0%200%20800%20600'%3E%3Crect%20width='800'%20height='600'%20fill='%232563eb'/%3E%3Ctext%20x='400'%20y='300'%20font-family='Arial'%20font-size='60'%20fill='white'%20text-anchor='middle'%20dominant-baseline='middle'%3EZLOCK%3C/text%3E%3C/svg%3E"
     alt="ZIP-LOCK пакеты с логотипом" 
     class="video-fallback">
                    </video>
                    <div class="video-overlay"></div>
                </div>
                
                <div class="container">
                    <div class="hero-grid">
                        <div class="hero-content" style="padding-left: 30px; padding-right: 30px;">
                            <!--<div class="hero-badge">
                                <i class="fas fa-badge-check"></i>
                                <span>Производитель ZIP-LOCK пакетов с логотипом</span>
                            </div>-->
                            
                            <h1 class="hero-title">
                                Зип лок пакеты <span class="text-gradient">с логотипом</span><br>
                                на заказ от 4 дней
                            </h1>
                            
                            <p class="hero-description">
                                Нанесём ваш логотип на ZIP-LOCK пакеты любых размеров. <br><br>
                                Собственное производство, флексопечать, любой тираж от 1000 штук.
                            </p>
                            
                            <div class="hero-actions">
                                <a href="#contact" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paint-brush"></i>
                                    Заказать с логотипом
                                </a>
                                <a href="#prices" class="btn btn-outline btn-lg">
                                    <i class="fas fa-tags"></i>
                                    Посмотреть цены
                                </a>
                            </div>
                            
                            <div class="hero-features">
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Печать до 4 цветов</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Бесплатный дизайн-макет</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>От 1000 штук</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Доставка по РФ</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===== БЛОК 2: ПРЕИМУЩЕСТВА ПЕЧАТИ НА ZIP-LOCK ===== -->
            <section class="advantages-section" id="advantages">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Печать логотипа на ZIP-LOCK пакетах</h2>
                        <p class="section-subtitle">Профессиональное нанесение изображений на упаковку</p>
                    </div>
                    
                    <div class="advantages-grid">
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-print"></i>
                            </div>
                            <h3>Флексопечать</h3>
                            <p>Современное оборудование для чёткой и яркой печати</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            <h3>До 4 цветов</h3>
                            <p>Полноцветная печать с высоким разрешением</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-ruler"></i>
                            </div>
                            <h3>Любые размеры</h3>
                            <p>Печатаем на пакетах от 4×6 до 50×70 см</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3>Быстрое изготовление</h3>
                            <p>Тиражи с печатью от 3-5 рабочих дней</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-pen-fancy"></i>
                            </div>
                            <h3>Дизайн бесплатно</h3>
                            <p>Разработаем макет под ваши требования</p>
                        </div>
                        
                        <div class="advantage-card">
                            <div class="advantage-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3>Гарантия качества</h3>
                            <p>Печать не стирается и не выцветает</p>
                        </div>
                    </div>
                </div>
            </section>
            
<!-- ===== БЛОК 3: ПОПУЛЯРНЫЕ РАЗМЕРЫ С ЦЕНАМИ (ПАКЕТЫ С БЕГУНКОМ) ===== -->
<section class="special-offer-section" id="prices">
    <div class="container">
        <div class="special-offer-header">
            <div class="offer-badge">
                <i class="fas fa-tag"></i>
                <span>ZIP-LOCK с логотипом</span>
            </div>
            <h2 class="section-title">Пакеты с бегунком (слайдеры) с печатью логотипа</h2>
            <p class="section-subtitle">Цены указаны за пакет с нанесением логотипа (1 цвет)</p>
        </div>

        <div class="offer-grid">
            <!-- Пакеты слайдеры с бегунком и логотипом -->
            <div class="offer-category">
                <div class="category-header">
                    <h3><i class="fas fa-sliders-h"></i> Пакеты слайдеры с бегунком и логотипом</h3>
                    <div class="category-badge">Хит продаж</div>
                </div>
                
                <div class="products-grid">


                    <!-- 30*35 см -->
                    <div class="product-offer-card">
                        <div class="product-image-mini">
                            <img src="images/begun.png" alt="Слайдер пакет 30×35 см с логотипом" loading="lazy">
                            <div class="image-overlay-logo">
                                <i class="fas fa-crown" style="position: absolute; top: 10px; right: 10px; background: gold; color: #333; border-radius: 50%; padding: 8px; font-size: 1rem;"></i>
                            </div>
                        </div>
                        <div class="product-size">
                            <span class="size-value">30 × 35 см</span>
                            <span class="size-thickness">60 мкм + логотип</span>
                        </div>
                        <div class="product-prices">
                            <div class="price-row">
                                <span class="price-label">от 10 000 шт:</span>
                                <span class="price-value">5.8 ₽/шт</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label">от 50 000 шт:</span>
                                <span class="price-value">5.2 ₽/шт</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label">от 100 000 шт:</span>
                                <span class="price-value">4.9 ₽/шт</span>
                            </div>
                        </div>
                        <div class="product-tag">
                            <i class="fas fa-print"></i> Печать 1-2 цвета
                        </div>
                        <button class="btn btn-primary btn-block add-to-cart" data-product-id="slider-logo-30-35" data-size="30*35" data-type="slider-logo">
                            <i class="fas fa-shopping-cart"></i>
                            Добавить в заявку
                        </button>
                    </div>

                    <!-- 35*45 см -->
                    <div class="product-offer-card">
                        <div class="product-image-mini">
                            <img src="images/begun.png" alt="Слайдер пакет 35×45 см с логотипом" loading="lazy">
                            <div class="image-overlay-logo">
                                <i class="fas fa-crown" style="position: absolute; top: 10px; right: 10px; background: gold; color: #333; border-radius: 50%; padding: 8px; font-size: 1rem;"></i>
                            </div>
                        </div>
                        <div class="product-size">
                            <span class="size-value">35 × 45 см</span>
                            <span class="size-thickness">60 мкм + логотип</span>
                        </div>
                        <div class="product-prices">
                            <div class="price-row">
                                <span class="price-label">от 10 000 шт:</span>
                                <span class="price-value">8.5 ₽/шт</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label">от 50 000 шт:</span>
                                <span class="price-value">7.8 ₽/шт</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label">от 100 000 шт:</span>
                                <span class="price-value">6.9 ₽/шт</span>
                            </div>
                        </div>
                        <div class="product-tag">
                            <i class="fas fa-print"></i> Печать 1-2 цвета
                        </div>
                        <button class="btn btn-primary btn-block add-to-cart" data-product-id="slider-logo-35-45" data-size="35*45" data-type="slider-logo">
                            <i class="fas fa-shopping-cart"></i>
                            Добавить в заявку
                        </button>
                    </div>

                    <!-- 40*50 см -->
                    <div class="product-offer-card">
                        <div class="product-image-mini">
                            <img src="images/begun.png" alt="Слайдер пакет 40×50 см с логотипом" loading="lazy">
                            <div class="image-overlay-logo">
                                <i class="fas fa-crown" style="position: absolute; top: 10px; right: 10px; background: gold; color: #333; border-radius: 50%; padding: 8px; font-size: 1rem;"></i>
                            </div>
                        </div>
                        <div class="product-size">
                            <span class="size-value">40 × 50 см</span>
                            <span class="size-thickness">70 мкм + логотип</span>
                        </div>
                        <div class="product-prices">
                            <div class="price-row">
                                <span class="price-label">от 10 000 шт:</span>
                                <span class="price-value">10.5 ₽/шт</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label">от 50 000 шт:</span>
                                <span class="price-value">9.5 ₽/шт</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label">от 100 000 шт:</span>
                                <span class="price-value">8.5 ₽/шт</span>
                            </div>
                        </div>
                        <div class="product-tag">
                            <i class="fas fa-print"></i> Печать до 4 цветов
                        </div>
                        <button class="btn btn-primary btn-block add-to-cart" data-product-id="slider-logo-40-50" data-size="40*50" data-type="slider-logo">
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
                        <h4>Нужен другой размер или полноцветная печать?</h4>
                        <p>Изготовим пакеты-слайдеры с логотипом по вашим индивидуальным параметрам</p>
                    </div>
                    <a href="#contact" class="btn btn-outline">
                        <i class="fas fa-calculator"></i>
                        Рассчитать свой размер
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Сравнение материалов EVA и ПВД -->
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
                                <span class="price-value">от 2.5 ₽/шт</span>
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
                                <span class="price-value">от 1.35 ₽/шт</span>
                            </div>
                        </div>
                    </div>

        
        <div class="offer-cta" style="margin-top: 4rem;">
            <div class="cta-content">
                <h3>Хотите заказать пакеты-слайдеры с логотипом?</h3>
                <p>Получите индивидуальную скидку при заказе от 150,000 рублей. Специальные условия для постоянных клиентов!</p>
                
                <div class="cta-benefits">
                    <div class="benefit">
                        <i class="fas fa-percentage"></i>
                        <span>Скидка до 30% на крупные тиражи</span>
                    </div>
                    <div class="benefit">
                        <i class="fas fa-truck"></i>
                        <span>Бесплатная доставка от 500 000 шт</span>
                    </div>
                    <div class="benefit">
                        <i class="fas fa-gift"></i>
                        <span>Бесплатные образцы с печатью</span>
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

            <!-- ===== БЛОК 5: ПРОИЗВОДСТВО С ПЕЧАТЬЮ ===== -->
            <section class="production-section" id="production">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Как мы наносим логотип</h2>
                        <p class="section-subtitle">Процесс производства ZIP-LOCK пакетов с печатью</p>
                    </div>
                    
                    <div class="production-steps">
                        <div class="step">
                            <div class="step-number">01</div>
                            <div class="step-content">
                                <h3>Консультация</h3>
                                <p>Обсуждаем размеры, тираж, количество цветов и расположение логотипа</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">02</div>
                            <div class="step-content">
                                <h3>Дизайн</h3>
                                <p>Создаём макет с вашим логотипом, утверждаем с клиентом</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">03</div>
                            <div class="step-content">
                                <h3>Изготовление клише</h3>
                                <p>Создаём печатные формы для флексопечати</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">04</div>
                            <div class="step-content">
                                <h3>Печать</h3>
                                <p>Наносим логотип на плёнку методом флексопечати</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">05</div>
                            <div class="step-content">
                                <h3>Вырубка и сварка</h3>
                                <p>Формируем пакеты с замком ZIP-LOCK</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">06</div>
                            <div class="step-content">
                                <h3>Упаковка и доставка</h3>
                                <p>Контроль качества и отправка заказа</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- ===== БЛОК 6: ПРИМЕРЫ ПЕЧАТИ ===== -->
            <section class="gallery-section" style="padding: 4rem 0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Примеры нанесения логотипов</h2>
                        <p class="section-subtitle">Реальные работы наших клиентов</p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 3rem;">
                        <div style="background: white; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow);">
                            <div style="height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-crown" style="font-size: 4rem; color: gold;"></i>
                            </div>
                            <div style="padding: 1.5rem;">
                                <h3>Одноцветная печать</h3>
                                <p>Чёткое нанесение логотипа одним цветом. Идеально для брендирования.</p>
                            </div>
                        </div>
                        
                        <div style="background: white; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow);">
                            <div style="height: 200px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); display: flex; align-items: center; justify-content: center;">
                                <div style="color: white; font-size: 2rem; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">LOGO</div>
                            </div>
                            <div style="padding: 1.5rem;">
                                <h3>Двухцветная печать</h3>
                                <p>Комбинирование двух цветов для яркого и запоминающегося дизайна.</p>
                            </div>
                        </div>
                        
                        <div style="background: white; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow);">
                            <div style="height: 200px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); display: flex; align-items: center; justify-content: center; gap: 10px;">
                                <i class="fas fa-star" style="color: white; font-size: 2rem;"></i>
                                <i class="fas fa-star" style="color: white; font-size: 3rem;"></i>
                                <i class="fas fa-star" style="color: white; font-size: 2rem;"></i>
                            </div>
                            <div style="padding: 1.5rem;">
                                <h3>Полноцветная печать</h3>
                                <p>До 4 цветов для сложных изображений и фотографий.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- ===== БЛОК 7: CTA ===== -->
            <section class="cta-section">
                <div class="container">
                    <div class="cta-content">
                        <h2>Готовы заказать ZIP-LOCK пакеты с логотипом?</h2>
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
                            <h2>Заказать ZIP-LOCK с логотипом</h2>
                            <p>Отправьте заявку и получите коммерческое предложение с образцами в течение часа</p>
                            
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
                                    <textarea name="message" placeholder="Опишите ваш заказ: размер, тираж, количество цветов логотипа..." rows="4"></textarea>
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

            <!-- ===== БЛОК 9: SEO-БЛОК ===== -->
            <div class="seo-products-section" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 4rem 0; margin-top: 4rem;">
                <div class="container">
                    <div style="text-align: center; margin-bottom: 3rem;">
                        <h2 style="color: var(--dark); font-size: 2rem; margin-bottom: 1rem;">
                            <i class="fas fa-print" style="color: var(--primary); margin-right: 10px;"></i>
                            ZIP-LOCK пакеты с логотипом от производителя
                        </h2>
                        <p style="color: var(--gray-600); font-size: 1.125rem; max-width: 800px; margin: 0 auto;">
                            Производство и печать на ZIP-LOCK пакетах оптом и в розницу
                        </p>
                    </div>
                    
                    <div style="margin-top: 4rem; padding: 3rem; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); text-align: left;">
                        <h3 style="color: var(--dark); font-size: 1.75rem; margin-bottom: 1.5rem;">
                            <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 10px;"></i>
                            Всё о ZIP-LOCK пакетах с логотипом
                        </h3>
                        
                        <div style="margin-top: 2.5rem; padding-top: 2rem; border-top: 2px solid #f1f5f9;">
                            <p style="color: var(--gray-700); font-size: 1.125rem; max-width: 900px; margin: 0px 45px; line-height: 1.7; text-align:left;">
                                Компания ZLOCK специализируется на производстве ZIP-LOCK пакетов с нанесением логотипа. 
                                Мы используем метод флексопечати, который обеспечивает высокое качество и стойкость изображения. 
                                В нашем ассортименте представлены пакеты различных размеров: от 4×6 см до 50×70 см, 
                                с толщиной от 35 до 100 мкм. Возможно нанесение логотипа в 1-4 цвета. 
                                Минимальный тираж — от 1000 штук. Работаем с розничными и оптовыми клиентами, 
                                предоставляя индивидуальные условия сотрудничества. Бесплатно разработаем макет 
                                и предоставим образцы продукции.
                            </p>
                        </div>
                        
                        <div style="margin-top: 3rem;">
                            <h4 style="font-size: 1.3rem; margin-bottom: 1.5rem;">Преимущества печати на ZIP-LOCK пакетах:</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                                <div style="display: flex; align-items: start; gap: 1rem;">
                                    <i class="fas fa-check-circle" style="color: #10b981; font-size: 1.5rem;"></i>
                                    <div>
                                        <strong>Узнаваемость бренда</strong>
                                        <p style="color: var(--gray-600); margin-top: 0.5rem;">Ваш логотип всегда на виду у клиентов</p>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: start; gap: 1rem;">
                                    <i class="fas fa-check-circle" style="color: #10b981; font-size: 1.5rem;"></i>
                                    <div>
                                        <strong>Профессиональный вид</strong>
                                        <p style="color: var(--gray-600); margin-top: 0.5rem;">Упаковка выглядит солидно и дорого</p>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: start; gap: 1rem;">
                                    <i class="fas fa-check-circle" style="color: #10b981; font-size: 1.5rem;"></i>
                                    <div>
                                        <strong>Защита от подделок</strong>
                                        <p style="color: var(--gray-600); margin-top: 0.5rem;">Фирменная упаковка выделяет ваш продукт</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ключевые слова для SEO -->
                    <div style="margin-top: 2rem; display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center;">
                        <span style="padding: 0.5rem 1rem; background: #e0f2fe; color: #0369a1; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> зип лок пакеты с логотипом
                        </span>
                        <span style="padding: 0.5rem 1rem; background: #dcfce7; color: #166534; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> zip-lock пакеты с печатью
                        </span>
                        <span style="padding: 0.5rem 1rem; background: #fef3c7; color: #92400e; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> пакеты с замком и логотипом
                        </span>
                        <span style="padding: 0.5rem 1rem; background: #ede9fe; color: #5b21b6; border-radius: 20px; font-size: 0.875rem; font-weight: 500;">
                            <i class="fas fa-hashtag"></i> печать на zip пакетах
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