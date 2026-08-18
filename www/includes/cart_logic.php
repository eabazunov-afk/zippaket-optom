<?php
// Чистые функции корзины/заказа. Без сессии и БД.
require_once __DIR__ . '/cart_quantity.php';
// home_tiers() — единый источник оптовых ступеней (WHOLESALE_TIERS из config).
// Витрина (index.php), XLS-прайс (price_export.php) и корзина берут ступени отсюда,
// поэтому цена в корзине не может разойтись с обещанием витрины.
require_once __DIR__ . '/home_view.php';

// Верхняя граница количества одной позиции. Защищает line_total от переполнения
// DECIMAL(15,2): 10 млн шт × даже 100 000 ₽/шт = 1e12 < 1e13.
if (!defined('CART_MAX_QTY')) {
    define('CART_MAX_QTY', 10000000);
}

/**
 * Нижняя граница количества для оптовой ступени.
 * Приоритет — явный ключ min_qty; если его нет, разбираем подпись
 * («Опт от 300к» → 300000, «Розница от 3к» → 3000, «от 20 000» → 20000).
 */
function wholesale_tier_min_qty(array $tier): int
{
    if (isset($tier['min_qty'])) {
        return max(0, (int)$tier['min_qty']);
    }
    $label = (string)($tier['label'] ?? '');
    if (!preg_match('/от\s*([\d][\d\s]*)\s*(к|k|тыс)?/ui', $label, $m)) {
        return 0;
    }
    $n = (int)preg_replace('/\D/', '', $m[1]);
    if (!empty($m[2])) {
        $n *= 1000;
    }
    return $n;
}

/** Нормализовать список ступеней: min_qty + mult, по убыванию порога. */
function wholesale_normalize_tiers(array $rows): array
{
    $tiers = [];
    foreach ($rows as $t) {
        if (!is_array($t) || !isset($t['mult'])) {
            continue;
        }
        $tiers[] = [
            'label'   => (string)($t['label'] ?? ''),
            'mult'    => (float)$t['mult'],
            'min_qty' => wholesale_tier_min_qty($t),
        ];
    }
    usort($tiers, static fn($a, $b) => $b['min_qty'] <=> $a['min_qty']);
    return $tiers;
}

/** Оптовые ступени из конфигурации (отсортированы по убыванию порога). */
function wholesale_tiers(): array
{
    return wholesale_normalize_tiers(home_tiers());
}

/**
 * Ступень опта по количеству. Чистая: список ступеней можно передать явно.
 * Если ни одна ступень не подходит — розница без скидки (mult = 1.0).
 */
function wholesale_tier_for_qty(int $qty, ?array $tiers = null): array
{
    $tiers = wholesale_normalize_tiers($tiers ?? home_tiers());
    foreach ($tiers as $t) {
        if ($qty >= $t['min_qty']) {
            return $t;
        }
    }
    return ['label' => '', 'mult' => 1.0, 'min_qty' => 0];
}

/** Множитель ступени по количеству. */
function wholesale_multiplier(int $qty, ?array $tiers = null): float
{
    return wholesale_tier_for_qty($qty, $tiers)['mult'];
}

/** Цена за штуку с учётом ступени, округлённая до копейки. */
function wholesale_unit_price(float $base, int $qty, ?array $tiers = null): float
{
    return round($base * wholesale_multiplier($qty, $tiers), 2);
}

/** Ограничить количество разумным диапазоном [0, CART_MAX_QTY]. */
function cart_clamp_qty(int $qty): int
{
    if ($qty < 0) {
        return 0;
    }
    return min($qty, CART_MAX_QTY);
}

/**
 * Разбор количества из формы. Отличает «пустое поле» от явного «0» (=удалить).
 * @return array{ok:bool, qty:int, error:string, clamped:bool}
 */
function cart_parse_qty_input($raw): array
{
    $fail = static fn(string $e) => ['ok' => false, 'qty' => 0, 'error' => $e, 'clamped' => false];
    if ($raw === null || is_array($raw)) {
        return $fail('missing');
    }
    $s = trim((string)$raw);
    if ($s === '') {
        return $fail('empty');
    }
    if (!preg_match('/^\d+$/', $s)) {
        return $fail('not_a_number');
    }
    $raw_int = (int)$s;               // строка длиннее PHP_INT_MAX даёт PHP_INT_MAX
    $qty = cart_clamp_qty($raw_int);
    return ['ok' => true, 'qty' => $qty, 'error' => '', 'clamped' => $qty !== $raw_int];
}

/**
 * Строка корзины/заказа. Цена уже с применённой оптовой ступенью — именно она
 * уходит в снапшот order_items.price_snapshot.
 */
function cart_build_line(array $product, int $qty, ?array $tiers = null): array
{
    $min  = (int)($product['min_order_qty'] ?? 1);
    $step = (int)($product['qty_step'] ?? 1);
    $normQty = normalize_quantity(cart_clamp_qty($qty), $min, $step);

    $base = isset($product['price_rub']) && $product['price_rub'] !== null
        ? (float)$product['price_rub'] : 0.0;
    // Цена не задана («Цена по запросу») — позицию нельзя оформить, см. cart_checkout_issues().
    $priceMissing = $base <= 0;

    $tier  = wholesale_tier_for_qty($normQty, $tiers);
    $price = $priceMissing ? 0.0 : round($base * (float)$tier['mult'], 2);

    $stock = isset($product['stock_quantity']) && $product['stock_quantity'] !== null
        ? (int)$product['stock_quantity'] : null;
    $backorder = ($stock !== null && $normQty > $stock) ? $normQty - $stock : 0;

    return [
        'product_id'    => (int)$product['id'],
        'name'          => (string)$product['full_name'],
        'base_price'    => $base,
        'price'         => $price,
        'tier_label'    => $priceMissing ? '' : (string)$tier['label'],
        'tier_mult'     => $priceMissing ? 1.0 : (float)$tier['mult'],
        'qty'           => $normQty,
        'line_total'    => round($price * $normQty, 2),
        'price_missing' => $priceMissing,
        'available'     => !$priceMissing,
        'stock_qty'     => $stock,
        'backorder_qty' => $backorder,
    ];
}

/** Псевдо-строка для товара, который исчез из каталога (деактивирован/удалён). */
function cart_unavailable_line(int $productId, int $qty, string $name = ''): array
{
    return [
        'product_id'    => $productId,
        'name'          => $name !== '' ? $name : 'Товар снят с продажи',
        'base_price'    => 0.0,
        'price'         => 0.0,
        'tier_label'    => '',
        'tier_mult'     => 1.0,
        'qty'           => max(0, $qty),
        'line_total'    => 0.0,
        'price_missing' => true,
        'available'     => false,
        'stock_qty'     => null,
        'backorder_qty' => 0,
        'gone'          => true,
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

/**
 * Причины, по которым корзину нельзя оформить (сообщения пользователю).
 * Пустой массив = оформление разрешено.
 */
function cart_checkout_issues(array $lines): array
{
    if (!$lines) {
        return []; // пустая корзина — отдельный случай, обрабатывается вызывающим кодом
    }
    $issues = [];
    foreach ($lines as $l) {
        $name = (string)($l['name'] ?? '');
        if (!empty($l['gone'])) {
            $issues[] = 'Позиция «' . $name . '» больше не продаётся — удалите её из корзины.';
        } elseif (empty($l['available'])) {
            $issues[] = 'Для позиции «' . $name . '» цена по запросу — удалите её из корзины и запросите расчёт у менеджера.';
        }
    }
    if (!$issues) {
        $t = cart_totals($lines);
        if ($t['items_total'] <= 0) {
            $issues[] = 'Сумма заказа равна нулю — оформить его нельзя. Свяжитесь с менеджером для расчёта цены.';
        }
    }
    return $issues;
}

/**
 * Предупреждения о нехватке остатка. Не блокируют заказ: производство под заказ,
 * недостающее количество изготавливается, срок согласует менеджер.
 */
function cart_stock_warnings(array $lines): array
{
    $warnings = [];
    foreach ($lines as $l) {
        if ((int)($l['backorder_qty'] ?? 0) > 0) {
            $warnings[] = '«' . (string)($l['name'] ?? '') . '»: в наличии '
                . number_format((int)$l['stock_qty'], 0, ',', ' ') . ' шт, остальные '
                . number_format((int)$l['backorder_qty'], 0, ',', ' ')
                . ' шт — под заказ, срок изготовления согласует менеджер.';
        }
    }
    return $warnings;
}

function order_number(int $seq, ?int $ts = null): string
{
    $ts = $ts ?? time();
    return 'ZP-' . date('Ymd', $ts) . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}
