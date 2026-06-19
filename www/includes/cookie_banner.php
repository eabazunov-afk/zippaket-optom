<?php
// Cookie-баннер для всех страниц (кроме index.php — там свой). Self-contained.
if (defined('COOKIE_BANNER_RENDERED')) { return; }
define('COOKIE_BANNER_RENDERED', true);
?>
<div id="cookieConsent" class="cookie-consent" style="display:none">
    <div class="container">
        <div class="cookie-content">
            <div class="cookie-text">
                <i class="fas fa-cookie-bite"></i>
                <p>Мы используем файлы cookie для улучшения работы сайта. Продолжая пользоваться сайтом, вы соглашаетесь с
                   <a href="/polconf.html" target="_blank">Политикой конфиденциальности</a> и
                   <a href="/cookie-policy.php" target="_blank">Политикой использования cookie</a>.</p>
            </div>
            <div class="cookie-actions">
                <button id="acceptCookies" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Принять все</button>
                <button id="rejectCookies" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Отклонить</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function init() {
        var c = document.getElementById('cookieConsent');
        if (!c || c.dataset.bound) return;
        c.dataset.bound = '1';
        if (!localStorage.getItem('cookiesAccepted')) {
            setTimeout(function () { c.style.display = 'block'; setTimeout(function () { c.classList.add('show'); }, 100); }, 1200);
        }
        var acc = document.getElementById('acceptCookies'), rej = document.getElementById('rejectCookies');
        function hide() { c.classList.remove('show'); setTimeout(function () { c.style.display = 'none'; }, 400); }
        if (acc) acc.addEventListener('click', function () { localStorage.setItem('cookiesAccepted', 'all'); localStorage.setItem('analyticsCookies', 'true'); hide(); });
        if (rej) rej.addEventListener('click', function () { localStorage.setItem('cookiesAccepted', 'none'); localStorage.setItem('analyticsCookies', 'false'); hide(); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
