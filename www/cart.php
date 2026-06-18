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
</head>
<body>
<div class="site-wrapper">
    <?php include __DIR__ . '/header.php'; ?>
    <main class="main-content">
        <section class="catalog-section"><div class="container">
            <h1>Корзина</h1>
            <?php if (empty($lines)): ?>
                <div class="no-products">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Корзина пуста.</p>
                    <a href="/katalog_zip_paketov" class="btn btn-primary">В каталог</a>
                </div>
            <?php else: ?>
                <table class="cart-table" style="width:100%;border-collapse:collapse">
                    <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr data-id="<?= (int)$l['product_id'] ?>" style="border-bottom:1px solid #eef2f6">
                            <td style="padding:10px;width:60px">
                                <img src="<?= htmlspecialchars($l['image_url']) ?>" alt="" style="width:48px;height:48px;object-fit:contain;background:#f8fafc;border-radius:8px">
                            </td>
                            <td style="padding:10px">
                                <div><?= htmlspecialchars($l['name']) ?></div>
                                <small style="color:#94a3b8"><?= htmlspecialchars(pv_pack_note($l['min_order_qty'], $l['qty_step'])) ?></small>
                            </td>
                            <td style="padding:10px;white-space:nowrap"><?= $l['price'] > 0 ? pv_format_price($l['price']) : 'по запросу' ?></td>
                            <td style="padding:10px">
                                <input type="number" class="cart-qty filter-input small" value="<?= (int)$l['qty'] ?>" min="<?= (int)$l['min_order_qty'] ?>" step="<?= (int)$l['qty_step'] ?>" data-id="<?= (int)$l['product_id'] ?>" style="width:100px">
                            </td>
                            <td style="padding:10px;white-space:nowrap;font-weight:700"><?= pv_format_price($l['line_total']) ?></td>
                            <td style="padding:10px">
                                <button class="cart-remove btn btn-outline btn-sm" data-id="<?= (int)$l['product_id'] ?>"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;flex-wrap:wrap;gap:12px">
                    <div style="font-size:1.2rem">Итого: <b><?= pv_format_price($totals['items_total']) ?></b> <small style="color:#94a3b8">(доставка согласуется отдельно)</small></div>
                    <a href="/checkout.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Оформить заказ</a>
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
