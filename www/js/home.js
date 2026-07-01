/* home.js — анимации главной ZLOCK: scroll-reveal, счётчики, прогресс-бары, таймер.
   Чистый JS, без зависимостей. Дата окончания акции приходит из PHP (#z-sale[data-end]). */
(function () {
    'use strict';

    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function animateCount(el) {
        if (el.dataset.counted) return;
        el.dataset.counted = '1';
        var target = parseFloat(el.getAttribute('data-count')) || 0;
        var prefix = el.getAttribute('data-prefix') || '';
        var suffix = el.getAttribute('data-suffix') || '';
        if (reduce) { el.textContent = prefix + target + suffix; return; }
        var dur = 1400, start = performance.now();
        function step(now) {
            var t = Math.min(1, (now - start) / dur);
            var eased = 1 - Math.pow(1 - t, 3);
            el.textContent = prefix + Math.round(target * eased) + suffix;
            if (t < 1) requestAnimationFrame(step); else el.textContent = prefix + target + suffix;
        }
        requestAnimationFrame(step);
    }

    function reveal(el) {
        if (el.classList.contains('z-in')) return;   // идемпотентно: без повторной анимации счётчиков
        el.classList.add('z-in');
        var bar = el.querySelector('[data-bar]');
        if (bar) setTimeout(function () { bar.style.width = (parseFloat(bar.getAttribute('data-bar')) || 0) + '%'; }, 260);
        var counts = el.querySelectorAll('[data-count]');
        for (var i = 0; i < counts.length; i++) animateCount(counts[i]);
    }

    function initReveal() {
        var els = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
        if (!('IntersectionObserver' in window)) {
            els.forEach(reveal);
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { io.unobserve(e.target); reveal(e.target); }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
        els.forEach(function (el) { io.observe(el); });

        // Подстраховка от «пропавшего» текста: при быстром скролле или переходе
        // по #якорю IntersectionObserver может не зафиксировать пересечение, и
        // элемент навсегда остаётся при opacity:0. Sweep гарантированно показывает
        // всё, что уже находится в зоне видимости или прокручено мимо.
        function sweep() {
            var vh = window.innerHeight || document.documentElement.clientHeight;
            document.querySelectorAll('[data-reveal]:not(.z-in)').forEach(function (el) {
                // Полный vh (а не 0.92): иначе элемент, чей верх попал в нижние 8%
                // экрана у самого низа страницы, не показать — доскроллить уже некуда.
                if (el.getBoundingClientRect().top < vh) { io.unobserve(el); reveal(el); }
            });
        }
        var ticking = false;
        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () { ticking = false; sweep(); });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        window.addEventListener('load', sweep);
        sweep(); // первичный проход: above-the-fold + переход по #anchor
    }

    function pad(n) { return String(n).padStart(2, '0'); }

    function initTimer() {
        var root = document.getElementById('z-sale');
        if (!root) return;
        var end = parseInt(root.getAttribute('data-end'), 10);
        if (!end) return;
        var d = document.getElementById('t-days'),
            h = document.getElementById('t-hours'),
            m = document.getElementById('t-mins'),
            s = document.getElementById('t-secs');
        function tick() {
            var diff = Math.max(0, end - Date.now());
            var dd = Math.floor(diff / 86400000); diff -= dd * 86400000;
            var hh = Math.floor(diff / 3600000); diff -= hh * 3600000;
            var mm = Math.floor(diff / 60000); diff -= mm * 60000;
            var ss = Math.floor(diff / 1000);
            if (d) d.textContent = pad(dd);
            if (h) h.textContent = pad(hh);
            if (m) m.textContent = pad(mm);
            if (s) s.textContent = pad(ss);
        }
        tick();
        setInterval(tick, 1000);
    }

    function initCalcModal() {
        var modal = document.getElementById('calcModal');
        if (!modal) return;
        function open() { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
        function close() { modal.classList.remove('open'); document.body.style.overflow = ''; }
        // Любая ссылка, ведущая на #calculator (включая /index.php#calculator), открывает модалку
        var links = document.querySelectorAll('a[href$="#calculator"]');
        for (var i = 0; i < links.length; i++) {
            links[i].addEventListener('click', function (e) { e.preventDefault(); open(); });
        }
        var closers = modal.querySelectorAll('[data-calc-close]');
        for (var j = 0; j < closers.length; j++) { closers[j].addEventListener('click', close); }
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        // Открыть, если пришли по якорю с другой страницы
        if (window.location.hash === '#calculator') open();
    }

    function init() { initReveal(); initTimer(); initCalcModal(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
