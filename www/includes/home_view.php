<?php
/**
 * Чистые view-хелперы главной. Без БД и без вывода — только форматирование
 * и отбор, чтобы покрыть тестами (tests/HomeViewTest.php).
 */

/** Цена уровня из базовой цены и множителя: "3 849,40". */
function home_price(float $base, float $mult): string {
    return number_format($base * $mult, 2, ',', ' ');
}

/** Размер из мм в "25 × 30 см". Пустая строка, если размеров нет. */
function home_size(?int $w, ?int $h): string {
    $fmt = function ($mm) {
        return rtrim(rtrim(number_format($mm / 10, 1, '.', ''), '0'), '.');
    };
    if (!$w || !$h) return '';
    return $fmt($w) . ' × ' . $fmt($h) . ' см';
}

/** Новинки: по created_at убыванию, первые $limit. Не мутирует вход. */
function home_pick_new(array $products, int $limit = 4): array {
    usort($products, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return array_slice($products, 0, $limit);
}

/** Хиты: по quantity_sold убыв., при равенстве — по stock_quantity убыв. */
function home_pick_hits(array $products, int $limit = 8): array {
    usort($products, function ($a, $b) {
        $s = (int)($b['quantity_sold'] ?? 0) <=> (int)($a['quantity_sold'] ?? 0);
        return $s !== 0 ? $s : ((int)($b['stock_quantity'] ?? 0) <=> (int)($a['stock_quantity'] ?? 0));
    });
    return array_slice($products, 0, $limit);
}

/** Опт-уровни: из WHOLESALE_TIERS (config) или дефолт. */
function home_tiers(): array {
    if (defined('WHOLESALE_TIERS') && is_array(WHOLESALE_TIERS)) {
        return WHOLESALE_TIERS;
    }
    return [
        ['label' => 'Опт от 300к', 'mult' => 0.82, 'class' => 'p-main'],
        ['label' => 'Опт от 20к',  'mult' => 0.92, 'class' => 'p-sec'],
        ['label' => 'Розница от 3к','mult' => 1.0,  'class' => 'p-sec'],
    ];
}
