<footer class="main-footer">
    <div class="container">
        <!-- Верхняя часть футера -->
        <div class="footer-top">
            <div class="footer-brand">
                <a href="/" class="logo">
                    <div class="logo-icon">ZIP</div>
                    <div class="logo-text">
                        
                        <div class="logo-subtitle">Производство zip пакетов</div>
                    </div>
                </a>
                <p class="footer-description">
                    Ведущий производитель ZIP-пакетов в России. 
                    Собственное производство, высокое качество, индивидуальный подход.
                </p>
                <!-- ⚠️ ЗАГЛУШКА: ссылки соцсетей — подставить реальные URL -->
                <div class="footer-social">
                    <a href="#" class="social-link" aria-label="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="Telegram">
                        <i class="fab fa-telegram"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="ВКонтакте">
                        <i class="fab fa-vk"></i>
                    </a>
                    <a href="#" class="social-link" aria-label="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                </div>
            </div>
            
            <div class="footer-links">
                <div class="footer-column">
                    <h4 class="footer-title">Компания</h4>
                    <a href="/katalog_zip_paketov/" class="footer-link">Каталог</a>
                    <a href="/index.php#materials" class="footer-link">Материалы</a>
                    <a href="/index.php#calculator" class="footer-link">Калькулятор</a>
                    <a href="/index.php#contact" class="footer-link">Контакты</a>
                    <a href="/admin/" class="footer-link" target="_blank">Админ-панель</a>
                </div>
                
                <div class="footer-column">
                    <h4 class="footer-title">Продукция</h4>
                    <a href="/katalog_zip_paketov/" class="footer-link">Весь каталог</a>
                    <a href="/katalog_zip_paketov/" class="footer-link">Пакеты-слайдеры</a>
                    <a href="/katalog_zip_paketov/" class="footer-link">ZIP-Lock пакеты</a>
                    <a href="/zip_paket_s_logotipom" class="footer-link">Пакеты с логотипом</a>
                    <a href="/index.php#calculator" class="footer-link">Индивидуальный заказ</a>
                </div>
                
                <div class="footer-column">
                    <h4 class="footer-title">Услуги</h4>
                    <a href="/zip_paket_s_logotipom" class="footer-link">Дизайн упаковки</a>
                    <a href="/zip_paket_s_logotipom" class="footer-link">Печать любой сложности</a>
                    <a href="/dostavka-i-oplata.php" class="footer-link">Доставка по РФ</a>
                    <a href="/index.php#contact" class="footer-link">Консультация технолога</a>
                    <a href="/index.php#contact" class="footer-link">Бесплатные образцы</a>
                </div>
                
                <div class="footer-column">
                    <h4 class="footer-title">Контакты</h4>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <a href="tel:<?= preg_replace('/[^0-9+]/','', SELLER_PHONE) ?>"><?= htmlspecialchars(SELLER_PHONE) ?></a>
                                <span>Звонок по России</span>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <a href="mailto:<?= htmlspecialchars(SELLER_EMAIL) ?>"><?= htmlspecialchars(SELLER_EMAIL) ?></a>
                                <span>Электронная почта</span>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <span><?= htmlspecialchars(SELLER_WORKHOURS) ?></span>
                                <span>Московское время</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Реквизиты продавца — центрированной строкой (без переносов) -->
        <!-- ⚠️ ЗАГЛУШКА: заменить в config (SELLER_*) -->
        <div class="footer-requisites">
            <?= htmlspecialchars(SELLER_NAME) ?> · ИНН <?= htmlspecialchars(SELLER_INN) ?> · ОГРН <?= htmlspecialchars(SELLER_OGRN) ?> · <?= htmlspecialchars(SELLER_ADDRESS) ?>
        </div>

        <!-- Нижняя часть футера -->
        <div class="footer-bottom">
            <div class="footer-copyright">
                &copy; <?php echo date('Y'); ?> ZLOCK. Все права защищены.
            </div>
            
            <div class="footer-legal">
                <a href="/polconf.html" class="legal-link">Политика конфиденциальности</a>
                <a href="/oferta.php" class="legal-link">Публичная оферта</a>
                <a href="/dostavka-i-oplata.php" class="legal-link">Доставка и оплата</a>
                <a href="/vozvrat.php" class="legal-link">Возврат и обмен</a>
                <a href="/cookie-policy.php" class="legal-link">Cookie</a>
                <a href="/kontakty.php" class="legal-link">Контакты</a>
            </div>
            
            <div class="footer-payment">
                <div class="payment-methods">
                    <span>Принимаем к оплате:</span>
                    <div class="payment-icons">
                        <i class="fab fa-cc-visa" title="Visa"></i>
                        <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                        <i class="fab fa-cc-mir" title="Мир"></i>
                        <i class="fas fa-credit-card" title="Банковские карты"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
<?php include __DIR__ . '/includes/cookie_banner.php'; ?>