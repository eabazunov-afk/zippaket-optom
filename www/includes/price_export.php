<?php
require_once __DIR__ . '/home_view.php';

/** Матрица строк прайса: [0] — заголовок, далее строки товаров (только с ценой). */
function price_rows(array $products, array $tiers): array {
    $header = ['Наименование', 'Размер', 'Толщина, мкм', 'Мин. партия', 'Наличие'];
    foreach ($tiers as $t) { $header[] = (string)$t['label']; }
    $rows = [$header];
    foreach ($products as $p) {
        $base = (float)($p['price_rub'] ?? 0);
        if ($base <= 0) { continue; }
        $row = [
            (string)($p['full_name'] ?? ''),
            home_size(isset($p['width']) ? (int)$p['width'] : null, isset($p['height']) ? (int)$p['height'] : null),
            $p['thickness'] !== null ? (string)(int)$p['thickness'] : '',
            (string)(int)($p['min_order_qty'] ?? 1),
            (int)($p['stock_quantity'] ?? 0) > 0 ? 'в наличии' : 'под заказ',
        ];
        foreach ($tiers as $t) { $row[] = home_price($base, (float)$t['mult']); }
        $rows[] = $row;
    }
    return $rows;
}
