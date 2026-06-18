/**
 * Каталог товаров - скрипты для фильтрации и взаимодействия
 */

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация
    initFilters();
    initSort();
    initViewToggle();
    initAddToCart();
    initQuickView();
    
    /**
     * Инициализация фильтров
     */
    function initFilters() {
        const filterForm = document.getElementById('filterForm');
        const applyBtn = document.querySelector('.apply-filters');
        const resetBtn = document.querySelector('.reset-filters');
        
        // Обработчики для radio и checkbox
        document.querySelectorAll('.filter-option input, .filter-checkbox input').forEach(input => {
            input.addEventListener('change', function() {
                updateFilterInputs();
            });
        });
        
        // Обработчики для текстовых полей (с задержкой)
        document.querySelectorAll('.filter-input:not(.small)').forEach(input => {
            let timer;
            input.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(() => {
                    updateFilterInputs();
                }, 500);
            });
        });
        
        // Обработчики для полей размера
        document.querySelectorAll('.filter-input.small').forEach(input => {
            input.addEventListener('change', function() {
                updateFilterInputs();
            });
        });
        
        // Кнопка применения фильтров
        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                applyFilters();
            });
        }
        
        // Сброс фильтров
        if (resetBtn) {
            resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = '/katalog_zip_paketov';
            });
        }
        
        // Поиск по Enter
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    updateFilterInputs();
                    applyFilters();
                }
            });
        }
    }
    
    /**
     * Обновление скрытых полей формы
     */
    function updateFilterInputs() {
        const filterForm = document.getElementById('filterForm');
        if (!filterForm) return;
        
        // Категория
        const categoryRadio = document.querySelector('input[name="category"]:checked');
        if (categoryRadio) {
            document.getElementById('filterCategory').value = categoryRadio.value;
        }
        
        // Тип
        const typeRadio = document.querySelector('input[name="type"]:checked');
        if (typeRadio) {
            document.getElementById('filterType').value = typeRadio.value;
        }
        
        // Толщина
        const thicknessRadio = document.querySelector('input[name="thickness"]:checked');
        if (thicknessRadio) {
            document.getElementById('filterThickness').value = thicknessRadio.value;
        }
        
        // Цвет
        const colorRadio = document.querySelector('input[name="color"]:checked');
        if (colorRadio) {
            document.getElementById('filterColor').value = colorRadio.value;
        }
        
        // Размеры
        document.getElementById('filterMinWidth').value = document.querySelector('input[name="min_width"]')?.value || '';
        document.getElementById('filterMaxWidth').value = document.querySelector('input[name="max_width"]')?.value || '';
        document.getElementById('filterMinHeight').value = document.querySelector('input[name="min_height"]')?.value || '';
        document.getElementById('filterMaxHeight').value = document.querySelector('input[name="max_height"]')?.value || '';
        
        // В наличии
        const inStockCheck = document.querySelector('input[name="in_stock"]');
        document.getElementById('filterInStock').value = inStockCheck && inStockCheck.checked ? 'yes' : '';
        
        // Поиск
        document.getElementById('filterSearch').value = document.querySelector('input[name="search"]')?.value || '';
        
        // Сбрасываем страницу
        document.getElementById('filterPage').value = '1';
    }
    
    /**
     * Применение фильтров
     */
    function applyFilters() {
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.submit();
        }
    }
    
    /**
     * Инициализация сортировки
     */
    function initSort() {
        const sortSelect = document.getElementById('sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                document.getElementById('filterSort').value = this.value;
                document.getElementById('filterPage').value = '1';
                document.getElementById('filterForm').submit();
            });
        }
    }
    
    /**
     * ИСПРАВЛЕНО: Инициализация переключения вида
     */
    function initViewToggle() {
        const viewBtns = document.querySelectorAll('.view-btn');
        const productsGrid = document.getElementById('productsGrid');
        
        if (!viewBtns.length || !productsGrid) return;
        
        // Сначала проверяем, есть ли активная кнопка в HTML
        let hasActive = false;
        viewBtns.forEach(btn => {
            if (btn.classList.contains('active')) {
                hasActive = true;
                // Устанавливаем соответствующий вид
                const view = btn.dataset.view;
                productsGrid.className = 'products-grid';
                if (view === 'list') {
                    productsGrid.classList.add('products-list');
                }
            }
        });
        
        // Если нет активной кнопки, активируем grid по умолчанию
        if (!hasActive) {
            const gridBtn = document.querySelector('.view-btn[data-view="grid"]');
            if (gridBtn) {
                gridBtn.classList.add('active');
            }
        }
        
        viewBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const view = this.dataset.view;
                
                // Обновляем активную кнопку
                viewBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Меняем класс сетки
                productsGrid.className = 'products-grid';
                if (view === 'list') {
                    productsGrid.classList.add('products-list');
                }
                
                // Сохраняем в localStorage
                localStorage.setItem('catalogView', view);
                
                console.log('Вид изменен на:', view); // Для отладки
            });
        });
        
        // Восстанавливаем сохраненный вид
        const savedView = localStorage.getItem('catalogView');
        if (savedView === 'list' || savedView === 'grid') {
            const targetBtn = document.querySelector(`.view-btn[data-view="${savedView}"]`);
            if (targetBtn) {
                targetBtn.click();
            }
        }
    }
    
    /**
     * Инициализация добавления в корзину
     */
    function initAddToCart() {
        document.querySelectorAll('.add-to-cart, .add-to-cart-special').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const productId = this.dataset.id;
                const productName = this.dataset.name || 'Товар';
                const productPrice = this.dataset.price || 0;
                
                addToCart(productId, productName, productPrice);
            });
        });
    }
    
    /**
     * Функция добавления в корзину
     */
    function addToCart(id, name, price) {
        // Получаем текущую корзину из localStorage
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        
        // Проверяем, есть ли уже такой товар
        const existing = cart.find(item => item.id === id);
        
        if (existing) {
            existing.quantity = (existing.quantity || 1) + 1;
        } else {
            cart.push({
                id: id,
                name: name,
                price: parseFloat(price),
                quantity: 1
            });
        }
        
        // Сохраняем
        localStorage.setItem('cart', JSON.stringify(cart));
        
        // Показываем уведомление
        showNotification('Товар добавлен в заявку', 'success');
        
        // Обновляем счетчик корзины
        updateCartCounter();
        
        // Отправляем в аналитику
        if (typeof ym !== 'undefined') {
            ym(106644271, 'reachGoal', 'add_to_cart');
        }
    }
    
    /**
     * Обновление счетчика корзины
     */
    function updateCartCounter() {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const total = cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
        
        document.querySelectorAll('.cart-counter').forEach(counter => {
            counter.textContent = total;
            counter.style.display = total > 0 ? 'flex' : 'none';
        });
    }
    
    /**
     * Инициализация быстрого просмотра
     */
    function initQuickView() {
        const modal = document.getElementById('quickViewModal');
        const closeBtn = modal?.querySelector('.modal-close');
        
        document.querySelectorAll('.quick-view').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.dataset.id;
                quickView(productId);
            });
        });
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                modal.classList.remove('show');
            });
        }
        
        // Закрытие по клику вне модалки
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        });
    }
    
    /**
     * Быстрый просмотр товара
     */
    function quickView(productId) {
        const modal = document.getElementById('quickViewModal');
        const content = document.getElementById('quickViewContent');
        
        if (!modal || !content) return;
        
        // Показываем загрузку
        content.innerHTML = '<div class="loading-spinner">Загрузка...</div>';
        modal.classList.add('show');
        
        // Загружаем данные через AJAX
        fetch(`/api/product.php?id=${productId}&quick=1`)
            .then(response => response.text())
            .then(html => {
                content.innerHTML = html;
            })
            .catch(error => {
                content.innerHTML = '<div class="error">Ошибка загрузки</div>';
                console.error('Error:', error);
            });
    }
    
    /**
     * Показ уведомления
     */
    function showNotification(message, type = 'info') {
        // Создаем контейнер для уведомлений если его нет
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
            `;
            document.body.appendChild(container);
        }
        
        // Создаем уведомление
        const notification = document.createElement('div');
        notification.style.cssText = `
            background: ${type === 'success' ? '#10b981' : '#3b82f6'};
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        
        container.appendChild(notification);
        
        // Удаляем через 3 секунды
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
    
    // Добавляем стили для анимаций
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
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
            
            .loading-spinner {
                text-align: center;
                padding: 50px;
                color: #64748b;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Инициализация счетчика корзины при загрузке
    updateCartCounter();
});