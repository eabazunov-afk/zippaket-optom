<?php http_response_code(404); $pageTitle = 'Страница не найдена'; require __DIR__ . '/includes/page_head.php'; ?>

<div class="z-center" style="padding:20px 0 40px">
    <div style="font-family:var(--font-display);font-size:clamp(72px,14vw,140px);font-weight:700;line-height:1;color:var(--z-mint)">404</div>
    <p style="font-size:1.1rem;max-width:520px;margin:16px auto 0;color:var(--z-text-2)">Такой страницы нет. Возможно, товар снят с продажи или ссылка устарела.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px">
        <a href="/" class="btn btn-primary"><i class="fas fa-home"></i> На главную</a>
        <a href="/katalog_zip_paketov/" class="btn btn-outline"><i class="fas fa-box-open"></i> В каталог</a>
    </div>
</div>

<?php require __DIR__ . '/includes/page_foot.php'; ?>
