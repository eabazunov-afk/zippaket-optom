<?php
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/product_view.php';
$lines = cart_session_lines();
$totals = cart_totals($lines);
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>Корзина | ZLOCK</title>
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/catalog.css">
    <link rel="stylesheet" href="/css/premium.css">
    <link rel="stylesheet" href="/css/shop-dark.css">
</head>
<body class="premium zlock">
<div class="site-wrapper">
    <?php include __DIR__ . '/header.php'; ?>
    <main class="main-content">
        <section class="catalog-section"><div class="container">
            <div class="pm-pagehead"><h1>Корзина</h1><div class="pm-sub">Доставку менеджер согласует после оформления</div></div>
            <?php if (empty($lines)): ?>
                <div class="no-products">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Корзина пуста.</p>
                    <a href="/katalog_zip_paketov" class="btn btn-primary">В каталог</a>
                </div>
            <?php else: ?>
                <?php $totalQty = array_sum(array_map(fn($l) => (int)$l['qty'], $lines)); ?>
                <div style="overflow-x:auto">
                <table class="cart-table" style="width:100%;border-collapse:collapse;min-width:580px">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:14px 12px">Товар</th>
                            <th style="text-align:right;padding:14px 12px;white-space:nowrap">Цена за шт</th>
                            <th style="text-align:center;padding:14px 12px">Количество</th>
                            <th style="text-align:right;padding:14px 12px">Сумма</th>
                            <th style="padding:14px 12px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr data-id="<?= (int)$l['product_id'] ?>">
                            <td style="padding:14px 12px">
                                <div style="display:flex;align-items:center;gap:12px">
                                    <img src="<?= htmlspecialchars($l['image_url']) ?>" alt="" style="width:52px;height:52px;object-fit:contain;border-radius:8px;flex:none">
                                    <div>
                                        <div style="font-weight:600"><?= htmlspecialchars($l['name']) ?></div>
                                        <small style="color:var(--z-text-3,#94a3b8)"><?= htmlspecialchars(pv_pack_note($l['min_order_qty'], $l['qty_step'])) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:14px 12px;text-align:right;white-space:nowrap"><?= $l['price'] > 0 ? pv_format_price($l['price']) : 'по запросу' ?></td>
                            <td style="padding:14px 12px;text-align:center">
                                <input type="number" class="cart-qty filter-input small" value="<?= (int)$l['qty'] ?>" min="<?= (int)$l['min_order_qty'] ?>" step="<?= (int)$l['qty_step'] ?>" data-id="<?= (int)$l['product_id'] ?>" style="width:108px;text-align:center" aria-label="Количество">
                            </td>
                            <td style="padding:14px 12px;text-align:right;white-space:nowrap;font-weight:800;color:var(--z-mint,#0A8F8F)"><?= pv_format_price($l['line_total']) ?></td>
                            <td style="padding:14px 12px;text-align:center">
                                <button class="cart-remove btn btn-outline btn-sm" data-id="<?= (int)$l['product_id'] ?>" aria-label="Удалить"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div class="pm-summary" style="margin-top:24px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center">
                    <div>
                        <div style="display:flex;gap:28px;flex-wrap:wrap;margin-bottom:14px">
                            <div><small style="color:var(--z-text-2,#64748b)">Позиций</small><div style="font-weight:700;font-size:1.15rem"><?= count($lines) ?></div></div>
                            <div><small style="color:var(--z-text-2,#64748b)">Всего штук</small><div style="font-weight:700;font-size:1.15rem"><?= number_format($totalQty, 0, '', ' ') ?></div></div>
                        </div>
                        <div style="color:var(--z-text-2,#64748b);font-size:.9rem;line-height:1.6">
                            <i class="fas fa-credit-card" style="color:var(--z-mint,#0A8F8F)"></i>
                            Оплата: <b>картой онлайн</b> или <b>по счёту</b> (для юрлиц) — выбор на следующем шаге.<br>
                            <i class="fas fa-truck" style="color:var(--z-mint,#0A8F8F)"></i>
                            Доставку менеджер согласует после оформления.
                        </div>
                    </div>
                    <div style="text-align:right">
                        <div style="color:var(--z-text-2,#64748b);font-size:.9rem">К оплате</div>
                        <div class="pm-total" style="margin-bottom:14px;font-size:1.7rem"><?= pv_format_price($totals['items_total']) ?></div>
                        <a href="/checkout.php" class="btn btn-primary btn-lg"><i class="fas fa-arrow-right"></i> Перейти к оформлению</a>
                    </div>
                </div>
            <?php endif; ?>
        </div></section>
    </main>
    <?php include __DIR__ . '/footer.php'; ?>
</div>
<script src="/js/cart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  function send(action, id, qty) {
    var body = new URLSearchParams({ action: action, id: id, qty: qty || 0, csrf_token: csrf });
    return fetch('/api/cart.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function (r) { return r.json(); }).then(function () { location.reload(); });
  }
  document.querySelectorAll('.cart-qty').forEach(function (inp) {
    inp.addEventListener('change', function () { send('update', inp.dataset.id, inp.value); });
  });
  document.querySelectorAll('.cart-remove').forEach(function (b) {
    b.addEventListener('click', function () { send('remove', b.dataset.id, 0); });
  });
});
</script>
</body>
</html>
