<?php
// Плавающий виджет мессенджеров/обратного звонка (FAB).
// Ненавязчивый: НЕ авто-раскрывается, без таймеров/поп-апов. Раскрытие только по клику.
// Подключается один раз в footer.php → показывается на всех страницах с футером.
if (defined('CONTACT_FAB_RENDERED')) { return; }
define('CONTACT_FAB_RENDERED', true);

$fabPhone = defined('SUPPORT_PHONE') ? preg_replace('/[^0-9+]/', '', SUPPORT_PHONE) : '';
$fabPhoneLabel = defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '';
?>
<div class="z-fab" id="contactFab">
    <div class="z-fab-list" role="menu" aria-label="Связаться с нами">
        <a class="z-fab-item z-fab-tg" href="https://t.me/zlock_sales_bot" target="_blank" rel="noopener" role="menuitem">
            <i class="fab fa-telegram" aria-hidden="true"></i><span>Telegram</span>
        </a>
        <?php if ($fabPhone !== ''): ?>
        <a class="z-fab-item" href="tel:<?= htmlspecialchars($fabPhone) ?>" role="menuitem">
            <i class="fas fa-phone" aria-hidden="true"></i><span><?= htmlspecialchars($fabPhoneLabel) ?></span>
        </a>
        <?php endif; ?>
        <a class="z-fab-item" href="/#leadForm" role="menuitem">
            <i class="fas fa-phone-volume" aria-hidden="true"></i><span>Заказать звонок</span>
        </a>
    </div>
    <button type="button" class="z-fab-toggle" aria-expanded="false" aria-controls="contactFab" aria-label="Связаться с нами">
        <i class="fas fa-comments z-fab-ic-open" aria-hidden="true"></i>
        <i class="fas fa-xmark z-fab-ic-close" aria-hidden="true"></i>
    </button>
</div>
<script>
(function () {
    var fab = document.getElementById('contactFab');
    if (!fab || fab.dataset.bound) return;
    fab.dataset.bound = '1';
    var toggle = fab.querySelector('.z-fab-toggle');
    function setOpen(open) {
        fab.classList.toggle('open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    // Делегированный клик: тумблер по кнопке, закрытие по выбору пункта.
    fab.addEventListener('click', function (e) {
        if (e.target.closest('.z-fab-toggle')) { setOpen(!fab.classList.contains('open')); return; }
        if (e.target.closest('.z-fab-item')) { setOpen(false); }
    });
    // Клик вне виджета / Esc — закрыть. Никакого авто-раскрытия.
    document.addEventListener('click', function (e) {
        if (!fab.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>
