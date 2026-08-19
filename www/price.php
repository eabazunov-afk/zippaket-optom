<?php
require_once __DIR__ . '/includes/config.php';

/**
 * Лид-магнит «Скачать прайс».
 *  - GET /price.php            → тёмная форма захвата контакта (152-ФЗ + reCAPTCHA v3).
 *                                Отправка идёт на существующий пайплайн лидов
 *                                (/includes/api.php?action=save_lead, type=price_download).
 *  - GET /price.php?download=1 → сборка и отдача XLS из актуальных цен/остатков (без повторного захвата).
 *
 * ВАЖНО: НЕ подключаем includes/api.php — он самодиспетчеризуется на уровне файла.
 * Лид сохраняется через тот же save_lead (никакого дублирования INSERT-логики).
 */

// --- Ветка отдачи XLS ---------------------------------------------------------
if (isset($_GET['download'])) {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/includes/home_view.php';
    require_once __DIR__ . '/includes/catalog_functions.php';
    require_once __DIR__ . '/includes/price_export.php';

    $catalog = new Catalog();
    $all = $catalog->getProducts(['sort' => 'popular'], 1, 1000)['products'];
    $rows = price_rows($all, home_tiers());

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($rows, null, 'A1');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="price-zippaket-' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Скачать прайс-лист ZIP-пакетов | ZLOCK</title>
    <meta name="description" content="Скачайте актуальный прайс-лист на ZIP-пакеты в формате XLS: цены по уровням опта, размеры, наличие на складе.">
    <meta name="robots" content="noindex, follow">
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">

    <!-- reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
</head>
<body class="zlock">
    <div class="site-wrapper z-page">
        <div class="z-mesh"></div>
        <div class="z-veil"></div>
        <div class="z-grain"></div>

        <div class="z-content">
        <?php include __DIR__ . '/header.php'; ?>

        <main class="main-content">
            <section class="z-section">
                <div class="z-wrap" style="max-width:560px;margin:0 auto">
                    <div class="z-badge" style="margin-bottom:18px"><i class="ph ph-download-simple"></i><span>Прайс-лист в XLS</span></div>
                    <h1 class="z-h1" style="font-size:clamp(28px,4vw,40px)">Скачать актуальный прайс</h1>
                    <p class="z-hero-sub" style="margin-bottom:26px">Оставьте контакт — и мы сформируем свежий прайс-лист с ценами по уровням опта, размерами и наличием на складе. Файл откроется в Excel.</p>

                    <form id="priceForm" class="z-form">
                        <input type="text" name="name" placeholder="Ваше имя *" required autocomplete="name">
                        <input type="tel" name="phone" placeholder="Телефон *" required autocomplete="tel">
                        <input type="email" name="email" placeholder="Email" autocomplete="email">
                        <input type="hidden" name="type" value="price_download">
                        <input type="hidden" id="recaptchaToken" name="recaptcha_token">
                        <label class="z-consent"><input type="checkbox" name="pdn_consent" value="1" required> Я даю <a href="/polconf.html" target="_blank" style="color:var(--z-mint)">согласие на обработку персональных данных</a></label>
                        <button type="submit" class="z-btn z-btn-gold z-shine"><i class="ph ph-download-simple"></i>Скачать прайс</button>
                    </form>
                    <p id="priceFormMsg" style="margin-top:14px;font-size:14px;color:var(--z-text-3)" role="status"></p>
                </div>
            </section>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <script>
    (function () {
        var form = document.getElementById('priceForm');
        if (!form) return;
        var msg = document.getElementById('priceFormMsg');
        var SITE_KEY = '6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf';

        function say(text, ok) {
            if (!msg) return;
            msg.textContent = text;
            msg.style.color = ok ? 'var(--z-mint)' : '#ff6b6b';
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var name = form.querySelector('input[name="name"]');
            var phone = form.querySelector('input[name="phone"]');
            var consent = form.querySelector('input[name="pdn_consent"]');

            if (!name.value.trim()) { say('Пожалуйста, введите имя'); return; }
            if (!phone.value.trim()) { say('Пожалуйста, введите телефон'); return; }
            if (!consent.checked) { say('Нужно согласие на обработку персональных данных'); return; }

            var button = form.querySelector('button[type="submit"]');
            var originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Формируем…';
            say('');

            grecaptcha.ready(function () {
                grecaptcha.execute(SITE_KEY, { action: 'price_download' }).then(function (token) {
                    // Payload мирроринг save_lead (см. js/script.js): type=price_download, pdn_consent, recaptcha_token.
                    var data = {
                        name: name.value.trim(),
                        phone: phone.value.trim(),
                        email: (form.querySelector('input[name="email"]').value || '').trim(),
                        type: 'price_download',
                        source: 'price',
                        pdn_consent: true,
                        recaptcha_token: token,
                        parameters: { source: 'price' }
                    };

                    fetch('/includes/api.php?action=save_lead', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (result) {
                        if (result.success) {
                            say('Готово! Скачивание начнётся автоматически…', true);
                            window.location = '/price.php?download=1';
                        } else {
                            say(result.message || 'Ошибка при отправке');
                            button.disabled = false;
                            button.innerHTML = originalText;
                        }
                    })
                    .catch(function (err) {
                        console.error('Error:', err);
                        say('Ошибка при подключении к серверу');
                        button.disabled = false;
                        button.innerHTML = originalText;
                    });
                }).catch(function (err) {
                    console.error('reCAPTCHA error:', err);
                    say('Ошибка проверки безопасности');
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            });
        });
    })();
    </script>

    <script src="/js/script.js"></script>
</body>
</html>
