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
})();
