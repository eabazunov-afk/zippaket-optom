<?php
// Инициализация UTM трекера
if (file_exists(__DIR__ . '/includes/utm_tracker.php')) {
    require_once __DIR__ . '/includes/utm_tracker.php';
    UTMTracker::init();
}
?>

<!-- Верхняя панель -->
<div class="top-bar">
    <div class="container">
        <div class="top-bar-content">
            <div class="contacts">
                <a href="tel:+79203465067">
                    <i class="fas fa-phone"></i>
                    +7 (920) 346-50-67
                </a>
                <a href="mailto:ZTR37@Bk.ru">
                    <i class="fas fa-envelope"></i>
                    ZTR37@Bk.ru
                </a>
            </div>
            <div class="work-hours">
                <i class="fas fa-clock"></i>
                Пн-Пт: 9:00-18:00
            </div>
        </div>
    </div>
</div>

<!-- Основная шапка -->
<header class="main-header">
    <div class="container">
        <div class="header-content">
            <!-- Логотип -->
            <a href="/" class="logo">
                <img src="/images/logo_zip_optom.svg" alt="ZLOCK - Производство zip пакетов оптом" class="logo-svg">
            </a>

            <!-- Навигация -->
            <nav class="main-nav">
                <a href="/index.php#special-offer" class="nav-link">Спецпредложение</a>
                <a href="/index.php#calculator" class="nav-link">Калькулятор</a>
                <a href="/zip_paket_s_logotipom" class="nav-link">С логотипом</a>
                <a href="/index.php#production" class="nav-link">Производство</a>
                <a href="/index.php#contact" class="nav-link">Контакты</a>
            </nav>

            <!-- Кнопка заказа -->
            <button class="btn btn-primary" id="headerCallback" type="button">
                <i class="fas fa-phone"></i>
                Заказать звонок
            </button>

            <!-- Гамбургер меню с четырьмя квадратами - ИСПРАВЛЕНО -->
            <button class="hamburger-menu" id="hamburgerMenu" aria-label="Открыть меню" type="button">
                <!-- Вместо div используем span или прямо SVG -->
                <span class="hamburger-inner">
                    <span class="square square-1"></span>
                    <span class="square square-2"></span>
                    <span class="square square-3"></span>
                    <span class="square square-4"></span>
                </span>
            </button>
        </div>
    </div>
</header>

<!-- Мобильное меню -->
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <div class="logo-container">
            <img src="/images/logo_zip_optom.svg" alt="ZLOCK - Производство zip пакетов оптом" class="logo-svg">
        </div>
        <button class="mobile-close" id="mobileClose" type="button">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <nav class="mobile-nav">
        <a href="#special-offer" class="mobile-nav-link">
            <i class="fas fa-star"></i>
            <span>Спецпредложение</span>
        </a>
        <a href="/index.php#calculator" class="mobile-nav-link">
            <i class="fas fa-calculator"></i>
            <span>Калькулятор</span>
        </a>


        <a href="/zip_paket_s_logotipom" class="mobile-nav-link">
             <i class="fas fa-ravelry" aria-hidden="true"></i>
            <span>С логотипом</span>
        </a>


        <a href="#production" class="mobile-nav-link">
            <i class="fas fa-industry"></i>
            <span>Производство</span>
        </a>
        <a href="#contact" class="mobile-nav-link">
            <i class="fas fa-map-marker-alt"></i>
            <span>Контакты</span>
        </a>
    </nav>
    
    <div class="mobile-contacts">
        <div class="mobile-contact-item">
            <i class="fas fa-phone"></i>
            <div>
                <strong><a href="tel:+79203465067">+7 (920) 346-50-67</a></strong>
            </div>
        </div>
        <div class="mobile-contact-item">
            <i class="fas fa-envelope"></i>
            <div>
                <strong><a href="mailto:ZTR37@Bk.ru">ZTR37@Bk.ru</a></strong>
            </div>
        </div>
        <div class="mobile-contact-item">
            <i class="fas fa-clock"></i>
            <div>
                <strong>Пн-Пт: 9:00-18:00</strong>
            </div>
        </div>
    </div>
    
    <button class="btn btn-primary btn-block" id="mobileCallback" type="button">
        <i class="fas fa-phone"></i>
        Заказать звонок
    </button>
</div>

<!-- Оверлей для меню -->
<div class="menu-overlay" id="menuOverlay"></div>