// RFQ («Запросить КП»): делегированный обработчик кнопок .js-rfq (карточки + hero).
// Прокручивает к #leadForm, предзаполняет поле сообщения позицией и помечает лид как type=rfq.
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.js-rfq') : null;
    if (!b) return;
    e.preventDefault();
    var form = document.getElementById('leadForm');
    if (!form) return;
    var msg = form.querySelector('[name="message"], [name="comment"]');
    if (msg && b.dataset.id) {
      msg.value = 'Запрос КП: ' + (b.dataset.name || '') + ' (#' + b.dataset.id + ')';
    }
    var type = form.querySelector('[name="type"]');
    if (type) type.value = 'rfq';
    form.scrollIntoView({ behavior: 'smooth' });
    var name = form.querySelector('[name="name"]');
    if (name) name.focus();
  });

  // --- Быстрый заказ в 1 клик (мини-модалка #quickModal) ---
  // Свой тост, чтобы не зависеть от showNotification (нет на главной). Без native alert/confirm.
  function quickToast(text) {
    var t = document.createElement('div');
    t.textContent = text;
    t.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:2000;'
      + 'background:rgba(10,16,26,.96);color:#fff;padding:12px 22px;border-radius:12px;'
      + 'border:1px solid rgba(255,255,255,.14);box-shadow:0 8px 30px rgba(0,0,0,.45);font-size:15px';
    document.body.appendChild(t);
    setTimeout(function () { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 2200);
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 2600);
  }

  function quickModal() { return document.getElementById('quickModal'); }
  function quickOpen() {
    var m = quickModal();
    if (!m) return;
    m.classList.add('open');
    m.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    var p = m.querySelector('.js-quick-phone');
    if (p) { p.value = ''; setTimeout(function () { p.focus(); }, 50); }
  }
  function quickClose() {
    var m = quickModal();
    if (!m) return;
    m.classList.remove('open');
    m.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  // Открытие по кнопке .js-quick — переносим data-id/name/min в модалку.
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.js-quick') : null;
    if (!b) return;
    e.preventDefault();
    var m = quickModal();
    if (!m) return;
    m.dataset.id = b.dataset.id || '';
    m.dataset.name = b.dataset.name || '';
    m.dataset.min = b.dataset.min || '1';
    var title = m.querySelector('.js-quick-title');
    if (title) title.textContent = b.dataset.name || 'Заказ в 1 клик';
    quickOpen();
  });

  // Закрытие: X / оверлей (data-quick-close).
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-quick-close]')) quickClose();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') quickClose();
  });

  // Отправка заказа — payload мирроринг /includes/api.php?action=save_lead (см. js/script.js).
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.js-quick-submit') : null;
    if (!btn) return;
    e.preventDefault();
    var m = quickModal();
    if (!m) return;
    var phoneEl = m.querySelector('.js-quick-phone');
    var phone = phoneEl ? phoneEl.value.trim() : '';
    if (!phone) { quickToast('Введите телефон'); if (phoneEl) phoneEl.focus(); return; }

    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';

    function restore() { btn.disabled = false; btn.innerHTML = original; }

    // Payload мирроринг save_lead (см. js/script.js): server требует recaptcha_token + pdn_consent.
    function send(token) {
      var data = {
        name: 'Быстрый заказ',
        phone: phone,
        type: 'quick_order',
        source: 'quick_order',
        pdn_consent: true,
        recaptcha_token: token || '',
        parameters: {
          product_id: parseInt(m.dataset.id, 10) || 0,
          name: m.dataset.name || '',
          qty: parseInt(m.dataset.min, 10) || 1
        }
      };
      fetch('/includes/api.php?action=save_lead', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
        .then(function (r) { return r.json(); })
        .then(function (result) {
          if (result && result.success) {
            quickToast('Заявка принята');
            quickClose();
          } else {
            quickToast((result && result.message) || 'Ошибка при отправке');
          }
          restore();
        })
        .catch(function (error) {
          console.error('quick_order error:', error);
          quickToast('Ошибка сети');
          restore();
        });
    }

    // Получаем reCAPTCHA-токен так же, как формы в script.js.
    if (typeof grecaptcha !== 'undefined' && grecaptcha.ready) {
      grecaptcha.ready(function () {
        grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', { action: 'quick_order' })
          .then(function (token) { send(token); })
          .catch(function (error) {
            console.error('reCAPTCHA error:', error);
            quickToast('Ошибка проверки безопасности');
            restore();
          });
      });
    } else {
      send('');
    }
  });
})();
