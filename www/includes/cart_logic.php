<?php
// Чистые функции корзины/заказа. Без сессии и БД.
require_once __DIR__ . '/cart_quantity.php';

function cart_build_line(array $product, int $qty): array
{
    $min  = (int)($product['min_order_qty'] ?? 1);
    $step = (int)($product['qty_step'] ?? 1);
    $normQty = normalize_quantity($qty, $min, $step);
    $price = isset($product['price_rub']) && $product['price_rub'] !== null
        ? (float)$product['price_rub'] : 0.0;
    return [
        'product_id' => (int)$product['id'],
        'name'       => (string)$product['full_name'],
        'price'      => $price,
        'qty'        => $normQty,
        'line_total' => round($price * $normQty, 2),
    ];
}

function cart_totals(array $lines): array
{
    $itemsTotal = 0.0;
    $totalQty = 0;
    foreach ($lines as $l) {
        $itemsTotal += (float)$l['line_total'];
        $totalQty += (int)$l['qty'];
    }
    return [
        'items_total' => round($itemsTotal, 2),
        'total_qty'   => $totalQty,
        'positions'   => count($lines),
    ];
}

function order_number(int $seq, ?int $ts = null): string
{
    $ts = $ts ?? time();
    return 'ZP-' . date('Ymd', $ts) . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}
