<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/reviews.php';
require_once __DIR__ . '/includes/recaptcha.php';

/**
 * Публичная форма отзыва.
 *  - GET  → тёмная форма (152-ФЗ + reCAPTCHA v3).
 *  - POST → CSRF + reCAPTCHA(action=review) + согласие ПДн → review_validate() → review_add().
 * Отзыв пишется на модерацию (is_approved=0), появляется на сайте после одобрения.
 */

$errors = [];
$old = [];
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['_'] = 'Сессия истекла, обновите страницу';
    } elseif (!recaptcha_verify($_POST['recaptcha_token'] ?? '', 'review')) {
        $errors['_'] = 'Проверка безопасности не пройдена';
    } elseif (empty($_POST['pdn_consent'])) {
        $errors['_'] = 'Подтвердите согласие на обработку персональных данных';
    } else {
        $v = review_validate($_POST);
        if (!$v['ok']) {
            $errors = $v['errors'];
        } else {
            $sent = review_add($v['data']);
            if (!$sent) {
                $errors['_'] = 'Не удалось сохранить отзыв, попробуйте позже';
            }
        }
    }
}

$csrf = generateCsrfToken();
function ra_old(array $old, string $k): string { return htmlspecialchars((string)($old[$k] ?? '')); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оставить отзыв | ZLOCK</title>
    <meta name="description" content="Оставьте отзыв о работе с ZLOCK: качество ZIP-пакетов, сервис и доставка. Отзыв публикуется после модерации.">
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
                    <div class="z-badge" style="margin-bottom:18px"><i class="fas fa-comment-dots"></i><span>Ваш отзыв</span></div>
                    <h1 class="z-h1" style="font-size:clamp(28px,4vw,40px)">Оставить отзыв</h1>
                    <p class="z-hero-sub" style="margin-bottom:26px">Расскажите о работе с нами — это помогает другим оптовикам и делает наш сервис лучше. Отзыв появится на сайте после модерации.</p>

                    <?php if ($sent): ?>
                        <div class="z-form" style="text-align:center">
                            <p style="font-size:18px;color:var(--z-mint);margin:0"><i class="fas fa-check-circle"></i> Спасибо! Отзыв появится после модерации.</p>
                            <a href="/" class="z-btn z-btn-gold z-shine" style="align-self:center"><i class="fas fa-arrow-left"></i>На главную</a>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors['_'])): ?>
                            <p style="margin-bottom:14px;color:var(--z-gold)" role="alert"><?= htmlspecialchars($errors['_']) ?></p>
                        <?php endif; ?>
                        <form id="reviewForm" class="z-form" method="POST" action="/review_add.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" id="reviewRecaptchaToken" name="recaptcha_token">

                            <input type="text" name="author_name" placeholder="Ваше имя *" maxlength="100" value="<?= ra_old($old, 'author_name') ?>" required>
                            <?php if (!empty($errors['author_name'])): ?><small style="color:var(--z-gold)"><?= htmlspecialchars($errors['author_name']) ?></small><?php endif; ?>

                            <input type="text" name="author_role" placeholder="Роль или компания (необязательно)" maxlength="120" value="<?= ra_old($old, 'author_role') ?>">

                            <select name="rating" style="padding:15px 18px;border-radius:12px;background:var(--z-surface-2);border:1px solid var(--z-hairline-strong);color:var(--z-text);font-size:15px;outline:none">
                                <?php for ($r = 5; $r >= 1; $r--): $selRating = (int)($old['rating'] ?? 5); ?>
                                    <option value="<?= $r ?>" <?= $selRating === $r ? 'selected' : '' ?>><?= review_stars($r) ?> — <?= $r ?> из 5</option>
                                <?php endfor; ?>
                            </select>

                            <textarea name="body" placeholder="Текст отзыва *" rows="5" maxlength="2000" required><?= ra_old($old, 'body') ?></textarea>
                            <?php if (!empty($errors['body'])): ?><small style="color:var(--z-gold)"><?= htmlspecialchars($errors['body']) ?></small><?php endif; ?>

                            <label class="z-consent"><input type="checkbox" name="pdn_consent" value="1" required> Я даю <a href="/polconf.html" target="_blank" style="color:var(--z-mint)">согласие на обработку персональных данных</a></label>
                            <button type="submit" class="z-btn z-btn-gold z-shine"><i class="fas fa-paper-plane"></i>Отправить отзыв</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <script>
    (function () {
        var form = document.getElementById('reviewForm');
        if (!form) return;
        var SITE_KEY = '6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf';
        form.addEventListener('submit', function (e) {
            if (form.dataset.recaptchaDone) return; // повторный сабмит после получения токена
            e.preventDefault();
            if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                grecaptcha.ready(function () {
                    grecaptcha.execute(SITE_KEY, { action: 'review' }).then(function (token) {
                        document.getElementById('reviewRecaptchaToken').value = token;
                        form.dataset.recaptchaDone = '1';
                        form.submit();
                    });
                });
            } else {
                form.dataset.recaptchaDone = '1';
                form.submit();
            }
        });
    })();
    </script>

    <script src="/js/script.js"></script>
</body>
</html>
