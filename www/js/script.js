// Общие скрипты сайта: мобильное меню, модалка обратного звонка, формы, hero-видео.
// Подключается ОДИН раз из footer.php (см. COMMON_SCRIPTS_RENDERED).
(function () {
// Защита от повторного подключения: отдельные страницы могут тянуть script.js
// своим тегом — обработчики не должны навешиваться дважды.
if (window.__zlockScriptJsLoaded) { return; }
window.__zlockScriptJsLoaded = true;

const RECAPTCHA_SITE_KEY = '6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf';

// Модалка обратного звонка теперь общая (footer.php) и живёт на страницах, где
// api.js в <head> не подключён (юр. страницы, корзина, checkout, 404).
// Грузим reCAPTCHA лениво — при первом открытии модалки, а не на каждой странице.
function ensureRecaptcha() {
    if (document.querySelector('script[src*="recaptcha/api.js"]')) { return; }
    const s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?render=' + RECAPTCHA_SITE_KEY;
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);
}

// Главная функция при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Элементы мобильного меню
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    const mobileMenu = document.getElementById('mobileMenu');
    const mobileClose = document.getElementById('mobileClose');
    const menuOverlay = document.getElementById('menuOverlay');

    // Функция переключения меню
    function toggleMenu() {
        if (hamburgerMenu) hamburgerMenu.classList.toggle('active');
        if (mobileMenu) mobileMenu.classList.toggle('active');
        if (menuOverlay) menuOverlay.classList.toggle('active');
        document.body.style.overflow = mobileMenu && mobileMenu.classList.contains('active') ? 'hidden' : '';
    }

    // Открытие/закрытие меню
    if (hamburgerMenu) {
        hamburgerMenu.addEventListener('click', toggleMenu);
    }

    if (mobileClose) {
        mobileClose.addEventListener('click', toggleMenu);
    }

    if (menuOverlay) {
        menuOverlay.addEventListener('click', toggleMenu);
    }

    document.querySelectorAll('.mobile-nav-link').forEach(link => {
        link.addEventListener('click', toggleMenu);
    });

    // Модальное окно заказа звонка
    const callbackButtons = document.querySelectorAll('#headerCallback, #mobileCallback');
    const modal = document.getElementById('callbackModal');
    // Крестик ищем ВНУТРИ своей модалки: document.querySelector('.modal-close') брал
    // первый крестик в документе (на каталоге — из quick-view), и модалка не закрывалась.
    const modalClose = modal ? modal.querySelector('.modal-close') : null;

    function openModal() {
        if (modal) {
            ensureRecaptcha();
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // Кнопка «Заказать звонок» без модалки на странице — прячем, чтобы не молчала.
    if (!modal) {
        callbackButtons.forEach(btn => { btn.style.display = 'none'; });
    }

    callbackButtons.forEach(btn => {
        btn.addEventListener('click', openModal);
    });

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
            if (mobileMenu && mobileMenu.classList.contains('active')) {
                toggleMenu();
            }
        }
    });

    // Анимация для гамбургера при наведении
    if (hamburgerMenu) {
        const squares = hamburgerMenu.querySelectorAll('.square');

        hamburgerMenu.addEventListener('mouseenter', function() {
            squares.forEach((square, index) => {
                setTimeout(() => {
                    square.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        square.style.transform = 'scale(1)';
                    }, 100);
                }, index * 50);
            });
        });
    }

    // reCAPTCHA v3 может быть заблокирована (адблок/корп. фильтр) — не роняем форму.
    function withRecaptcha(action, onToken, onError) {
        ensureRecaptcha();
        if (typeof grecaptcha === 'undefined' || typeof grecaptcha.ready !== 'function') {
            showNotification('Проверка безопасности не загрузилась. Отключите блокировщик рекламы или позвоните нам.', 'error');
            return;
        }
        grecaptcha.ready(function() {
            grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: action})
                .then(onToken)
                .catch(onError);
        });
    }

    // Форма заявки на главной странице
    const leadForm = document.getElementById('leadForm');
    if (leadForm && !leadForm.hasAttribute('data-handler-attached')) {
        leadForm.setAttribute('data-handler-attached', 'true');
        leadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Простая валидация
            const phone = this.querySelector('input[name="phone"]');
            const name = this.querySelector('input[name="name"]');

            if (!phone || !phone.value.trim()) {
                showNotification('Пожалуйста, введите телефон', 'error');
                return;
            }

            if (!name || !name.value.trim()) {
                showNotification('Пожалуйста, введите имя', 'error');
                return;
            }

            withRecaptcha('submit', function(token) {
                // Отправка через AJAX
                const formData = new FormData(leadForm);
                const data = Object.fromEntries(formData.entries());
                // Уважаем скрытое поле type (rfq.js ставит 'rfq' для «Запросить КП»),
                // по умолчанию — 'contact_form'.
                if (!data.type) data.type = 'contact_form';
                data.source = data.type === 'rfq' ? 'rfq_button' : 'main_form';
                data.recaptcha_token = token;

                // Добавляем comment из message если есть
                if (data.message && !data.comment) {
                    data.comment = data.message;
                }

                const button = leadForm.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;

                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
                button.disabled = true;

                fetch('/includes/api.php?action=save_lead', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showNotification('Заявка отправлена! Мы свяжемся с вами.', 'success');
                        leadForm.reset();
                    } else {
                        showNotification(result.message || 'Ошибка при отправке', 'error');
                    }
                })
                .catch(error => {
                    console.error('save_lead error:', error);
                    showNotification('Ошибка при подключении к серверу', 'error');
                })
                .finally(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }, function(error) {
                console.error('reCAPTCHA error:', error);
                showNotification('Ошибка проверки безопасности', 'error');
            });
        });
    }

    // Форма обратного звонка
    const callbackForm = document.getElementById('callbackForm');
    if (callbackForm && !callbackForm.hasAttribute('data-handler-attached')) {
        callbackForm.setAttribute('data-handler-attached', 'true');
        callbackForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Простая валидация
            const phone = this.querySelector('input[name="phone"]');
            if (!phone || !phone.value.trim()) {
                showNotification('Пожалуйста, введите телефон', 'error');
                return;
            }

            withRecaptcha('callback', function(token) {
                // Отправка через AJAX
                const formData = new FormData(callbackForm);
                const data = Object.fromEntries(formData.entries());
                data.type = 'callback';
                data.source = 'modal';
                data.recaptcha_token = token;

                // Добавляем comment из message если есть
                if (data.message && !data.comment) {
                    data.comment = data.message;
                }

                const button = callbackForm.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;

                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
                button.disabled = true;

                fetch('/includes/api.php?action=save_lead', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        showNotification('Спасибо! Мы перезвоним вам в течение 15 минут.', 'success');
                        callbackForm.reset();
                        closeModal();
                    } else {
                        showNotification(result.message || 'Ошибка при отправке', 'error');
                    }
                })
                .catch(error => {
                    console.error('save_lead error:', error);
                    showNotification('Ошибка при подключении к серверу', 'error');
                })
                .finally(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }, function(error) {
                console.error('reCAPTCHA error:', error);
                showNotification('Ошибка проверки безопасности', 'error');
            });
        });
    }

    // Кнопка запроса расчёта
    const requestBtn = document.getElementById('requestCalculation');
    if (requestBtn) {
        requestBtn.addEventListener('click', () => {
            const contact = document.getElementById('contact');
            if (contact) { contact.scrollIntoView({ behavior: 'smooth' }); }
        });
    }

    // Плавная прокрутка для якорных ссылок
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');

            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);

                if (target) {
                    window.scrollTo({
                        top: target.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // Анимация появления элементов при скролле
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.advantage-card, .product-card, .step').forEach(el => {
        observer.observe(el);
    });

    // Адаптация при изменении размера окна
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024 && mobileMenu && mobileMenu.classList.contains('active')) {
            toggleMenu();
        }
    });

    // Инициализация видео
    if (document.querySelector('.hero-video')) {
        initHeroVideo();
    }

    // Контроль качества видео на мобильных
    if (window.innerWidth <= 768) {
        const video = document.querySelector('.hero-video');
        if (video) {
            video.setAttribute('playsinline', '');
            video.setAttribute('muted', '');
            video.setAttribute('autoplay', '');
        }
    }

    // Функция для показа уведомлений
    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;

        document.body.appendChild(notification);

        setTimeout(() => notification.classList.add('show'), 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    };
});

// Управление видео в герой-секции
function initHeroVideo() {
    const heroVideo = document.querySelector('.hero-video');
    const heroSection = document.querySelector('.hero-section');

    if (!heroVideo || !heroSection) return;

    let isVideoPlaying = true;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                if (!isVideoPlaying) {
                    heroVideo.play().catch(() => { /* автоплей запрещён браузером — остаётся poster */ });
                    isVideoPlaying = true;
                }
            } else {
                if (isVideoPlaying) {
                    heroVideo.pause();
                    isVideoPlaying = false;
                }
            }
        });
    }, {
        threshold: 0.5
    });

    observer.observe(heroSection);

    heroVideo.load();

    setTimeout(() => {
        // Видео не догрузилось — прячем его, остаётся poster-кадр.
        if (heroVideo.readyState < 3) {
            heroVideo.style.display = 'none';
        }
    }, 3000);
}
})();
