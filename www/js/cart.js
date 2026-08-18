// Серверная корзина: добавление и счётчик.
(function () {
  // Защита от повторного подключения: скрипты общие (footer.php), но отдельные
  // страницы могут тянуть cart.js своим тегом — обработчики не должны дублироваться.
  if (window.__zlockCartJsLoaded) { return; }
  window.__zlockCartJsLoaded = true;

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }
  function refreshCounter(count) {
    document.querySelectorAll('.js-cart-counter').forEach(function (c) {
      c.textContent = count;
      c.style.display = count > 0 ? 'flex' : 'none';
    });
  }
  // Количество берём из БЛИЖАЙШЕГО к кнопке контейнера, а не глобальным #qty:
  // на карточке товара блок «похожие товары» имеет свои кнопки, и глобальный поиск
  // клал их в корзину в количестве основного товара.
  function resolveQty(btn) {
    var scope = btn.closest
      ? btn.closest('[data-qty-scope], .product-actions, .z-prod, .product-card, form')
      : null;
    var input = scope ? scope.querySelector('input[type="number"]') : null;
    if (input && input.value) { return input.value; }
    return btn.dataset.min || 1;
  }
  function post(params) {
    var body = new URLSearchParams(params);
    return fetch('/api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(function (r) { return r.json(); });
  }
  document.addEventListener('DOMContentLoaded', function () {
    post({ action: 'get' })
      .then(function (d) { if (d.success) refreshCounter(d.count); })
      .catch(function () { /* сеть/JSON недоступны — счётчик оставляем как есть */ });
  });
  // Делегирование: работает и для статичных, и для динамически добавленных кнопок (quick-view).
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.js-cart-add') : null;
    if (!btn) return;
    e.preventDefault();
    var id = btn.dataset.id;
    if (!id) return;
    var qty = resolveQty(btn);
    post({ action: 'add', id: id, qty: qty, csrf_token: csrf() }).then(function (d) {
      if (d.success) {
        refreshCounter(d.count);
        if (typeof ym !== 'undefined') { ym(106644271, 'reachGoal', 'add_to_cart'); }
        btn.classList.add('added');
        var html = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Добавлено';
        setTimeout(function () { btn.classList.remove('added'); btn.innerHTML = html; }, 1500);
      } else {
        // Сервер отказал осмысленно (нет цены, товар снят, некорректное количество) —
        // молчать нельзя: пользователь иначе жмёт кнопку повторно и считает сайт сломанным.
        var prev = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (d.message || 'Не удалось добавить');
        setTimeout(function () { btn.innerHTML = prev; }, 2500);
      }
    }).catch(function () {
      // Сбой сети/ответа: коротко сообщаем и возвращаем кнопку в исходное состояние.
      var html = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Ошибка сети';
      setTimeout(function () { btn.innerHTML = html; }, 1800);
    });
  });
})();
