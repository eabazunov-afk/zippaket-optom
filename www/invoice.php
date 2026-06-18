<?php
/**
 * Счёт на оплату для юрлиц (оплата «по счёту»).
 * Печатная HTML-форма — печать/сохранение в PDF средствами браузера (Ctrl+P).
 * Доступ: только для заказов payment_method=invoice.
 */
require_once __DIR__ . '/includes/order.php';
require_once __DIR__ . '/includes/product_view.php';

// Реквизиты продавца — ЗАПОЛНИТЬ реальными (можно вынести в config.php).
$seller = [
    'name' => defined('SELLER_NAME') ? SELLER_NAME : 'ООО «ЗЛОК»',
    'inn' => defined('SELLER_INN') ? SELLER_INN : '0000000000',
    'kpp' => defined('SELLER_KPP') ? SELLER_KPP : '000000000',
    'account' => defined('SELLER_ACCOUNT') ? SELLER_ACCOUNT : '00000000000000000000',
    'bank' => defined('SELLER_BANK') ? SELLER_BANK : 'Банк',
    'bik' => defined('SELLER_BIK') ? SELLER_BIK : '000000000',
    'corr' => defined('SELLER_CORR') ? SELLER_CORR : '00000000000000000000',
    'address' => defined('SELLER_ADDRESS') ? SELLER_ADDRESS : 'г. Москва',
    'phone' => defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '+7 (920) 346-50-67',
];

$orderNumber = isset($_GET['order']) ? preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['order']) : '';
$order = $orderNumber !== '' ? order_get_by_number($orderNumber) : null;

if ($order === null || $order['payment_method'] !== 'invoice') {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;padding:40px">Счёт не найден или заказ не предполагает оплату по счёту.</p>';
    exit;
}

$items = order_items_get((int)$order['id']);
$total = (float)$order['total'];
$invoiceNo = $order['order_number'];
$invoiceDate = !empty($order['created_at']) ? date('d.m.Y', strtotime($order['created_at'])) : date('d.m.Y');

/** Сумма прописью (рубли) — упрощённо. */
function rub_words(float $sum): string {
    $rub = (int)floor($sum);
    $kop = (int)round(($sum - $rub) * 100);
    return number_format($rub, 0, '', ' ') . ' руб. ' . str_pad((string)$kop, 2, '0', STR_PAD_LEFT) . ' коп.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Счёт № <?= htmlspecialchars($invoiceNo) ?> | ZLOCK</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111; background: #f3f4f6; margin: 0; padding: 24px; }
    .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .muted { color: #555; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 13px; }
    .req td { padding: 4px 8px; border: 1px solid #999; vertical-align: top; }
    .items th, .items td { border: 1px solid #999; padding: 8px; text-align: left; }
    .items th { background: #f1f5f9; }
    .items td.num, .items th.num { text-align: right; white-space: nowrap; }
    .total-row td { font-weight: 700; }
    .sign { margin-top: 36px; display: flex; justify-content: space-between; gap: 40px; font-size: 13px; }
    .sign .line { border-top: 1px solid #111; padding-top: 4px; width: 220px; text-align: center; color: #555; }
    .toolbar { max-width: 800px; margin: 0 auto 16px; display: flex; gap: 10px; }
    .btn { background: #0A8F8F; color: #fff; border: none; border-radius: 8px; padding: 10px 18px; font-size: 14px; cursor: pointer; text-decoration: none; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; } .toolbar { display: none; } }
</style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">🖨 Печать / Сохранить в PDF</button>
        <a class="btn" style="background:#475569" href="/order_success.php?order=<?= urlencode($invoiceNo) ?>">← К заказу</a>
    </div>
    <div class="sheet">
        <table class="req">
            <tr><td style="width:60%">
                <b><?= htmlspecialchars($seller['name']) ?></b><br>
                ИНН <?= htmlspecialchars($seller['inn']) ?> / КПП <?= htmlspecialchars($seller['kpp']) ?><br>
                <?= htmlspecialchars($seller['address']) ?><br>
                Тел.: <?= htmlspecialchars($seller['phone']) ?>
            </td><td>
                Р/с <?= htmlspecialchars($seller['account']) ?><br>
                <?= htmlspecialchars($seller['bank']) ?><br>
                БИК <?= htmlspecialchars($seller['bik']) ?><br>
                К/с <?= htmlspecialchars($seller['corr']) ?>
            </td></tr>
        </table>

        <h1>Счёт на оплату № <?= htmlspecialchars($invoiceNo) ?> от <?= htmlspecialchars($invoiceDate) ?></h1>

        <p class="muted">
            <b>Поставщик:</b> <?= htmlspecialchars($seller['name']) ?><br>
            <b>Покупатель:</b> <?= htmlspecialchars($order['company_name'] ?: $order['customer_name']) ?>
            <?= !empty($order['inn']) ? ', ИНН ' . htmlspecialchars($order['inn']) : '' ?>
            <?= !empty($order['kpp']) ? ', КПП ' . htmlspecialchars($order['kpp']) : '' ?>
            <?= !empty($order['legal_address']) ? ', ' . htmlspecialchars($order['legal_address']) : '' ?>
        </p>

        <table class="items">
            <thead>
                <tr>
                    <th>№</th><th>Наименование</th>
                    <th class="num">Кол-во</th><th class="num">Цена, ₽</th><th class="num">Сумма, ₽</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $it): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($it['name_snapshot']) ?></td>
                    <td class="num"><?= (int)$it['qty'] ?></td>
                    <td class="num"><?= number_format((float)$it['price_snapshot'], 2, ',', ' ') ?></td>
                    <td class="num"><?= number_format((float)$it['line_total'], 2, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4" class="num">Итого:</td>
                    <td class="num"><?= number_format($total, 2, ',', ' ') ?></td>
                </tr>
            </tbody>
        </table>

        <p><b>Всего к оплате:</b> <?= htmlspecialchars(rub_words($total)) ?></p>
        <p class="muted">Без НДС. Оплата настоящего счёта означает согласие с условиями поставки. Доставка согласуется отдельно.</p>

        <div class="sign">
            <div class="line">Руководитель</div>
            <div class="line">Бухгалтер</div>
        </div>
    </div>
</body>
</html>
