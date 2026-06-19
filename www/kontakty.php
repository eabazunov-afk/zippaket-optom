<?php $pageTitle = 'Контакты'; require __DIR__ . '/includes/page_head.php'; ?>

<div class="legal-stub"><i class="fas fa-triangle-exclamation"></i> <b>ЗАГЛУШКА.</b> Реквизиты и контакты подставляются из настроек (SELLER_*) — заменить на реальные.</div>

<div class="legal-req">
    <h3 style="margin-top:0">Продавец</h3>
    <p style="margin:0">
        <b><?= htmlspecialchars(SELLER_NAME) ?></b><br>
        ИНН <?= htmlspecialchars(SELLER_INN) ?> · ОГРН <?= htmlspecialchars(SELLER_OGRN) ?> · КПП <?= htmlspecialchars(SELLER_KPP) ?><br>
        Юридический адрес: <?= htmlspecialchars(SELLER_ADDRESS) ?>
    </p>
</div>

<h2>Связаться с нами</h2>
<ul>
    <li>Телефон: <a href="tel:<?= preg_replace('/[^0-9+]/', '', SELLER_PHONE) ?>"><?= htmlspecialchars(SELLER_PHONE) ?></a></li>
    <li>E-mail: <a href="mailto:<?= htmlspecialchars(SELLER_EMAIL) ?>"><?= htmlspecialchars(SELLER_EMAIL) ?></a></li>
    <li>Telegram: <a href="https://t.me/zlock_sales_bot" target="_blank" rel="noopener">@zlock_sales_bot</a></li>
    <li>Режим работы: <?= htmlspecialchars(SELLER_WORKHOURS) ?></li>
</ul>

<h2>Оставить заявку</h2>
<p>Быстрее всего — <a href="/index.php#contact">через форму на главной</a> или в Telegram-боте. Менеджер свяжется в течение 10 минут в рабочее время.</p>

<?php require __DIR__ . '/includes/page_foot.php'; ?>
