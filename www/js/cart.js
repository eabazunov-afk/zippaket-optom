// Серверная корзина: добавление и счётчик.
(function () {
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
  function post(params) {
    var body = new URLSearchParams(params);
    return fetch('/api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(function (r) { return r.json(); });
  }
  document.addEventListener('DOMContentLoaded', function () {
    post({ action: 'get' }).then(function (d) { if (d.success) refreshCounter(d.count); });
    document.querySelectorAll('.js-cart-add').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var id = btn.dataset.id;
        var qtyInput = document.getElementById('qty');
        var qty = qtyInput ? qtyInput.value : (btn.dataset.min || 1);
        post({ action: 'add', id: id, qty: qty, csrf_token: csrf() }).then(function (d) {
          if (d.success) {
            refreshCounter(d.count);
            if (typeof ym !== 'undefined') { ym(106644271, 'reachGoal', 'add_to_cart'); }
          }
        });
      });
    });
  });
})();
