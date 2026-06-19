// Объявляем класс OfferCart глобально (вне условий)
class OfferCart {
    constructor() {
        this.items = JSON.parse(localStorage.getItem('zip_offer_cart')) || [];
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.updateCartBadge();
    }
    
    bindEvents() {
        // Обработка кликов по кнопкам "Добавить в заявку"
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-to-cart') || e.target.closest('.add-to-cart')) {
                const button = e.target.classList.contains('add-to-cart') ? e.target : e.target.closest('.add-to-cart');
                this.addItem(button);
            }
        });
    }
    
    addItem(button) {
        const size = button.dataset.size || 'стандартный';
        const type = button.dataset.type || 'zip';
        const card = button.closest('.product-offer-card') || button.closest('.product-card');
        
        if (!card) return;
        
        // Получаем информацию о товаре
        const titleElement = card.querySelector('h3') || card.querySelector('h4');
        const title = titleElement ? titleElement.textContent : 'ZIP-пакет';
        
        const sizeElement = card.querySelector('.size-value');
        const thicknessElement = card.querySelector('.size-thickness');
        const priceElements = card.querySelectorAll('.price-value');
        
        const sizeValue = sizeElement ? sizeElement.textContent : `${size} см`;
        const thickness = thicknessElement ? thicknessElement.textContent : '80 мкм';
        
        // Извлекаем цены из таблицы
        const priceRows = card.querySelectorAll('.price-row');
        const prices = {
            opt300k: priceRows[0] ? parseFloat(priceRows[0].querySelector('.price-value').textContent) || 1.5 : 1.5,
            opt20k: priceRows[1] ? parseFloat(priceRows[1].querySelector('.price-value').textContent) || 2.0 : 2.0,
            retail: priceRows[2] ? parseFloat(priceRows[2].querySelector('.price-value').textContent) || 2.5 : 2.5
        };
        
        // Создаем объект товара
        const item = {
            id: `${type}_${size}_${Date.now()}`,
            type: type === 'slider' ? 'Пакет слайдер с бегунком' : 'Пакет с замком ZIP-LOCK',
            title: title,
            size: sizeValue,
            thickness: thickness,
            prices: prices,
            quantity: 1000,
            timestamp: Date.now()
        };
        
        // Добавляем в корзину
        const existingIndex = this.items.findIndex(i => i.size === item.size && i.type === item.type);
        if (existingIndex >= 0) {
            this.items[existingIndex].quantity += 1000;
        } else {
            this.items.push(item);
        }
        
        // Сохраняем в localStorage
        this.saveCart();
        
        // Обновляем бейдж
        this.updateCartBadge();
        
        // Показываем уведомление
        if (typeof showNotification === 'function') {
            showNotification(`${sizeValue} добавлен в заявку`, 'success');
        }
        
        // Анимация кнопки
        this.animateButton(button);
    }
    
    updateCartBadge() {
        // Плавающий офферный бейдж ОТКЛЮЧЁН: на сайте единая серверная корзина
        // (счётчик в шапке, .js-cart-counter). Убираем дубль, если он где-то остался.
        const old = document.querySelector('.cart-badge');
        if (old) { old.remove(); }
    }
    
    animateButton(button) {
        button.classList.add('added');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Добавлено!';
        
        setTimeout(() => {
            button.classList.remove('added');
            button.innerHTML = originalHTML;
        }, 2000);
    }
    
    showCartModal() {
        if (this.items.length === 0) {
            if (typeof showNotification === 'function') {
                showNotification('Корзина пуста', 'info');
            }
            return;
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal active';
        
        let itemsHTML = '';
        this.items.forEach((item, index) => {
            itemsHTML += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <h4>${item.type}</h4>
                        <div class="cart-item-details">
                            <span>${item.size}</span>
                            <span>${item.thickness}</span>
                        </div>
                        <div class="cart-item-prices">
                            <small>Цена от 300к: ${item.prices.opt300k} ₽/шт</small>
                        </div>
                    </div>
                    <div class="cart-item-actions">
                        <input type="number" 
                               class="quantity-input" 
                               value="${item.quantity}" 
                               min="1000" 
                               step="1000"
                               data-index="${index}"
                               placeholder="Кол-во">
                        <button class="btn btn-sm btn-danger remove-item" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        modal.innerHTML = `
            <div class="modal-content">
                <button class="modal-close"><i class="fas fa-times"></i></button>
                <h3>Ваша заявка (${this.items.length} товаров)</h3>
                <div class="cart-items">
                    ${itemsHTML}
                </div>
                <div class="cart-total">
                    <div class="total-label">Итого товаров:</div>
                    <div class="total-value" id="cartTotalItems">${this.items.reduce((sum, item) => sum + item.quantity, 0).toLocaleString('ru-RU')} шт</div>
                </div>
                <div class="cart-actions">
                    <button class="btn btn-outline" id="clearCart">
                        <i class="fas fa-trash"></i>
                        Очистить корзину
                    </button>
                    <button class="btn btn-primary" id="submitCart">
                        <i class="fas fa-paper-plane"></i>
                        Отправить заявку
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const closeBtn = modal.querySelector('.modal-close');
        closeBtn.addEventListener('click', () => modal.remove());
        
        // Обработка изменения количества
        modal.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', (e) => {
                const index = parseInt(e.target.dataset.index);
                const quantity = parseInt(e.target.value) || 1000;
                this.items[index].quantity = quantity;
                this.saveCart();
                this.updateCartBadge();
                
                const totalItems = this.items.reduce((sum, item) => sum + item.quantity, 0);
                modal.querySelector('#cartTotalItems').textContent = totalItems.toLocaleString('ru-RU') + ' шт';
            });
        });
        
        // Удаление товаров
        modal.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', (e) => {
                const index = parseInt(e.target.closest('.remove-item').dataset.index);
                this.items.splice(index, 1);
                this.saveCart();
                this.updateCartBadge();
                modal.remove();
                this.showCartModal();
            });
        });
        
        // Очистка корзины
        modal.querySelector('#clearCart').addEventListener('click', () => {
            if (confirm('Очистить всю корзину?')) {
                this.items = [];
                this.saveCart();
                this.updateCartBadge();
                modal.remove();
                if (typeof showNotification === 'function') {
                    showNotification('Корзина очищена', 'info');
                }
            }
        });
        
        // Отправка заявки
        modal.querySelector('#submitCart').addEventListener('click', () => {
            this.submitCart();
            modal.remove();
        });
    }
    
    saveCart() {
        localStorage.setItem('zip_offer_cart', JSON.stringify(this.items));
        
        // Также отправляем на сервер для логирования
        fetch('/includes/api.php?action=save_cart_items', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ items: this.items })
        }).catch(error => console.error('Ошибка сохранения корзины:', error));
    }
    
    submitCart() {
        if (this.items.length === 0) {
            if (typeof showNotification === 'function') {
                showNotification('Укажите количество товаров', 'error');
            }
            return;
        }
        
        this.showSubmitModal();
    }
    
    showSubmitModal() {
        const modal = document.createElement('div');
        modal.className = 'modal active';
        
        let itemsHTML = '';
        this.items.forEach((item, index) => {
            itemsHTML += `
                <div class="cart-item-preview">
                    <strong>${item.type}</strong>
                    <div>${item.size}, ${item.thickness}</div>
                    <div>Количество: ${item.quantity.toLocaleString('ru-RU')} шт</div>
                </div>
            `;
        });
        
        modal.innerHTML = `
            <div class="modal-content">
                <button class="modal-close"><i class="fas fa-times"></i></button>
                <h3>Отправка заявки</h3>
                <p>Введите ваши контактные данные для отправки заявки</p>
                
                <div class="cart-preview">
                    <h4>Товары в заявке (${this.items.length}):</h4>
                    ${itemsHTML}
                </div>
                
                <form id="leadFormModal">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Ваше имя *" required>
                    </div>
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="Телефон *" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Email">
                    </div>
                    <div class="form-group">
                        <textarea name="comment" placeholder="Комментарий к заказу..." rows="3"></textarea>
                    </div>
                    <input type="hidden" name="parameters" value='${JSON.stringify(this.items)}'>
                    <input type="hidden" name="type" value="cart">
                    <input type="hidden" name="source" value="cart">
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Отправить заявку
                    </button>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const closeBtn = modal.querySelector('.modal-close');
        closeBtn.addEventListener('click', () => modal.remove());
        
        const form = modal.querySelector('#leadFormModal');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendLeadData(form);
        });
    }
    
    sendLeadData(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Создаем объект для параметров
        let parameters = {};
        
        // Добавляем комментарий из формы если есть
        if (data.comment) {
            parameters['comment'] = data.comment;
        }
        
        // Добавляем все товары из корзины в параметры
        if (this.items && this.items.length > 0) {
            parameters['cart_items'] = this.items; // Сохраняем товары в отдельном ключе
            parameters['total_items_count'] = this.items.reduce((sum, item) => sum + (item.quantity || 0), 0);
            
            // Также добавляем сводную информацию о заказе
            const cartSummary = this.items.map(item => ({
                type: item.type,
                size: item.size,
                thickness: item.thickness,
                quantity: item.quantity || 1000,
                price_opt: item.prices?.opt300k || 0
            }));
            parameters['order_summary'] = JSON.stringify(cartSummary);
            
            data.type = 'cart'; // Устанавливаем тип заявки "корзина"
        }
        
        // Добавляем данные UTM если есть
        if (typeof window.utmData !== 'undefined') {
            parameters = {...parameters, ...window.utmData};
        }
        
        // Устанавливаем параметры в data
        data.parameters = parameters;
        
        const button = form.querySelector('button[type="submit"]');
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
                if (typeof showNotification === 'function') {
                    showNotification('Заявка отправлена! Мы свяжемся с вами.', 'success');
                }
                this.items = [];
                this.saveCart();
                this.updateCartBadge();
                
                // Закрываем все модальные окна
                document.querySelectorAll('.modal').forEach(modal => modal.remove());
            } else {
                if (typeof showNotification === 'function') {
                    showNotification(result.message || 'Ошибка при отправке', 'error');
                }
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof showNotification === 'function') {
                showNotification('Ошибка при подключении к серверу', 'error');
            }
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
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
    const modalClose = document.querySelector('.modal-close');
    
    function openModal() {
        if (modal) {
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
    
    // Форма заявки на главной странице (исправленная версия)
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
            
            grecaptcha.ready(function() {
                grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', {action: 'submit'}).then(function(token) {
                    // Отправка через AJAX
                    const formData = new FormData(leadForm);
                    const data = Object.fromEntries(formData.entries());
                    data.type = 'contact_form';
                    data.source = 'main_form';
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
                        console.error('Error:', error);
                        showNotification('Ошибка при подключении к серверу', 'error');
                    })
                    .finally(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
                }).catch(function(error) {
                    console.error('reCAPTCHA error:', error);
                    showNotification('Ошибка проверки безопасности', 'error');
                });
            });
        });
    }
    
    // Форма обратного звонка (исправленная версия)
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
            
            grecaptcha.ready(function() {
                grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', {action: 'callback'}).then(function(token) {
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
                        console.error('Error:', error);
                        showNotification('Ошибка при подключении к серверу', 'error');
                    })
                    .finally(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
                }).catch(function(error) {
                    console.error('reCAPTCHA error:', error);
                    showNotification('Ошибка проверки безопасности', 'error');
                });
            });
        });
    }
    
    // Кнопка запроса расчёта
    const requestBtn = document.getElementById('requestCalculation');
    if (requestBtn) {
        requestBtn.addEventListener('click', () => {
            document.getElementById('contact').scrollIntoView({
                behavior: 'smooth'
            });
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
    
    // Инициализация таймера
    if (document.getElementById('offerTimer')) {
        initOfferTimer();
        console.log('Таймер инициализирован');
    }
    
    // Инициализация корзины (с защитой от повторной инициализации)
    if (typeof window.offerCart === 'undefined') {
        window.offerCart = new OfferCart();
        console.log('Корзина инициализирована');
    }
    
    // Инициализация видео
    if (document.querySelector('.hero-video')) {
        initHeroVideo();
        console.log('Видео инициализировано');
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
                    heroVideo.play().catch(e => console.log('Не удалось воспроизвести видео:', e));
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
        if (heroVideo.readyState < 3) {
            console.log('Видео не загрузилось, показываем фолбэк');
            heroVideo.style.display = 'none';
        }
    }, 3000);
}

// Таймер обратного отсчета
function initOfferTimer() {
    const timerElement = document.getElementById('offerTimer');
    if (!timerElement) return;
    
    const endDate = new Date();
    endDate.setDate(endDate.getDate() + 25);
    endDate.setHours(23, 59, 59, 0);
    
    function updateTimer() {
        const now = new Date();
        const diff = endDate - now;
        
        if (diff <= 0) {
            timerElement.innerHTML = '<div class="timer-ended">Акция завершена!</div>';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        const timerValues = timerElement.querySelectorAll('.timer-value');
        if (timerValues[0]) timerValues[0].textContent = days.toString().padStart(2, '0');
        if (timerValues[1]) timerValues[1].textContent = hours.toString().padStart(2, '0');
        if (timerValues[2]) timerValues[2].textContent = minutes.toString().padStart(2, '0');
        if (timerValues[3]) timerValues[3].textContent = seconds.toString().padStart(2, '0');
    }
    
    updateTimer();
    const timerInterval = setInterval(updateTimer, 1000);
    
    window.offerTimerInterval = timerInterval;
}

// Стили для корзины и уведомлений (только если еще не добавлены)
if (!document.querySelector('#cart-styles')) {
    const cartStyles = `
        .cart-badge {
            position: fixed;
            top: 100px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary, #3b82f6) 0%, var(--primary-dark, #1d4ed8) 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            transition: all 0.3s ease;
            border: 3px solid white;
        }
        
        .cart-badge:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        
        .cart-badge:before {
            content: '🛒';
            position: absolute;
            top: -30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.8rem;
        }
        
        .cart-items {
            max-height: 400px;
            overflow-y: auto;
            margin: 1.5rem 0;
            padding-right: 10px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border: 1px solid #e5e7eb;
        }
        
        .cart-item-info h4 {
            margin-bottom: 0.5rem;
            font-size: 1rem;
            color: #1f2937;
        }
        
        .cart-item-details {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .cart-item-prices small {
            color: #10b981;
            font-weight: 500;
        }
        
        .cart-item-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .quantity-input {
            width: 100px;
            padding: 0.5rem;
            border: 2px solid #d1d5db;
            border-radius: 6px;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 8px;
            margin: 1.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .cart-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .cart-preview {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .cart-item-preview {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .cart-item-preview:last-child {
            border-bottom: none;
        }
        
        /* ФИКС ДЛЯ УВЕДОМЛЕНИЙ - убираем высоту 100vh */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 1003;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-width: 350px;
            width: auto;
            height: auto !important;
            min-height: auto !important;
            backdrop-filter: blur(10px);
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-left: 4px solid #047857;
        }
        
        .notification-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-left: 4px solid #b91c1c;
        }
        
        .notification-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-left: 4px solid #1e40af;
        }
        
        .add-to-cart.added {
            background: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }
    `;
    
    const styleSheet = document.createElement('style');
    styleSheet.id = 'cart-styles';
    styleSheet.textContent = cartStyles;
    document.head.appendChild(styleSheet);
}

// Функция обновления результатов калькулятора
function updateResults(data) {
    // Форматируем цены
    const basePriceFormatted = new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(data.base_price || 0);
    
    const unitPriceFormatted = new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(data.unit_price || 0);
    
    const totalPriceFormatted = new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(data.total_price || 0);
    
    const quantityFormatted = new Intl.NumberFormat('ru-RU').format(this.quantity);
    
    // Обновляем UI
    const basePriceElement = document.getElementById('basePrice');
    const unitPriceElement = document.getElementById('unitPrice');
    const totalPriceElement = document.getElementById('totalPrice');
    const discountInfoElement = document.getElementById('discountInfo');
    const quantityDisplayElement = document.getElementById('quantityDisplay');
    
    if (basePriceElement) basePriceElement.textContent = basePriceFormatted + ' ₽/шт';
    if (unitPriceElement) unitPriceElement.textContent = unitPriceFormatted + ' ₽/шт';
    if (totalPriceElement) totalPriceElement.textContent = totalPriceFormatted + ' ₽';
    if (quantityDisplayElement) quantityDisplayElement.textContent = quantityFormatted + ' шт';
    
    // Обновляем вес пакета
    if (this.weightInfoElement) {
        const weightFormatted = new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        }).format(data.weight_grams || 0);
        
        const weightKgFormatted = new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 4,
            maximumFractionDigits: 4
        }).format(data.weight_kg || 0);
        
        this.weightInfoElement.textContent = weightFormatted + ' г (' + weightKgFormatted + ' кг)';
    }
    
    // Обновляем информацию о скидке
    if (discountInfoElement) {
        const discount = data.discount_percent || 0;
        if (discount > 0) {
            const saved = (data.base_price * discount / 100).toFixed(2);
            discountInfoElement.innerHTML = `
                <span class="discount-badge">-${discount}%</span>
                <div class="discount-details">
                    <small>Экономия: ${saved} ₽/шт</small>
                </div>
            `;
            discountInfoElement.className = 'result-value has-discount';
        } else {
            discountInfoElement.textContent = 'Нет скидки';
            discountInfoElement.className = 'result-value';
        }
    }
    
    // Обновляем детализацию веса
    if (data.calculation_details) {
        const details = data.calculation_details;
        const weightBreakdownElement = document.getElementById('weightBreakdown');
        const filmWeightElement = document.getElementById('filmWeight');
        const zipperWeightElement = document.getElementById('zipperWeight');
        const sliderWeightElement = document.getElementById('sliderWeight');
        const totalWeightElement = document.getElementById('totalWeightDetail');
        
        if (weightBreakdownElement) weightBreakdownElement.style.display = 'block';
        if (filmWeightElement) {
            filmWeightElement.textContent = new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(details.film_weight) + ' г';
        }
        if (zipperWeightElement) {
            zipperWeightElement.textContent = new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(details.zipper_weight) + ' г';
        }
        if (sliderWeightElement) {
            sliderWeightElement.textContent = new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(details.slider_weight) + ' г';
        }
        if (totalWeightElement) {
            totalWeightElement.textContent = data.weight_grams + ' г';
        }
    }
    
    // Обновляем детализацию стоимости
    if (data.calculation_details && data.cost_breakdown) {
        const details = data.calculation_details;
        const breakdown = data.cost_breakdown;
        const costBreakdownElement = document.getElementById('costBreakdown');
        const weightDetailElement = document.getElementById('weightDetail');
        const materialPriceElement = document.getElementById('materialPrice');
        const basePriceDetailElement = document.getElementById('basePriceDetail');
        const discountPercentElement = document.getElementById('discountPercent');
        
        if (costBreakdownElement) costBreakdownElement.style.display = 'block';
        if (weightDetailElement) {
            weightDetailElement.textContent = data.weight_kg + ' кг';
        }
        if (materialPriceElement) {
            materialPriceElement.textContent = details.material_price_per_kg + ' ₽/кг';
        }
        if (basePriceDetailElement) {
            basePriceDetailElement.textContent = breakdown.base_price + ' ₽';
        }
        if (discountPercentElement) {
            discountPercentElement.textContent = breakdown.discount_percent + '%';
        }
    }
    
    // Обновляем примечание
    if (this.resultNoteElement && data.calculation_details) {
        const materialPrice = data.calculation_details.material_price_per_kg;
        this.resultNoteElement.innerHTML = `
            <i class="fas fa-info-circle"></i>
            <span>Цена материала: ${materialPrice} ₽/кг. Скидки: от 10к шт -15%, от 20к шт -20%, от 30к шт -30%</span>
        `;
    }
}