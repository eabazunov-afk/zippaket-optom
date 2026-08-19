<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/catalog_functions.php';
require_once __DIR__ . '/cart_quantity.php';
require_once __DIR__ . '/cart_logic.php';

function cart_session_raw(): array
{
    return isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
}

/**
 * Снапшот названий позиций корзины. Нужен, чтобы показать пользователю, какая
 * именно позиция стала недоступной, когда товар пропал из каталога.
 */
function cart_session_names(): array
{
    return isset($_SESSION['cart_names']) && is_array($_SESSION['cart_names']) ? $_SESSION['cart_names'] : [];
}

function cart_session_remember_name(int $productId, string $name): void
{
    if ($productId <= 0 || $name === '') { return; }
    $names = cart_session_names();
    $names[$productId] = $name;
    $_SESSION['cart_names'] = $names;
}

function cart_session_add(int $productId, int $qty): void
{
    if ($productId <= 0) { return; }
    $cart = cart_session_raw();
    $cart[$productId] = cart_clamp_qty(($cart[$productId] ?? 0) + max(1, $qty));
    $_SESSION['cart'] = $cart;
}

/**
 * Установить количество позиции. qty <= 0 означает ЯВНОЕ удаление позиции —
 * вызывающий код обязан отличать это от пустого поля ввода (см. cart_parse_qty_input()).
 */
function cart_session_set(int $productId, int $qty): void
{
    $cart = cart_session_raw();
    if ($qty <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = cart_clamp_qty($qty);
    }
    $_SESSION['cart'] = $cart;
}

function cart_session_remove(int $productId): void
{
    $cart = cart_session_raw();
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
    $names = cart_session_names();
    unset($names[$productId]);
    $_SESSION['cart_names'] = $names;
}

function cart_session_clear(): void
{
    $_SESSION['cart'] = [];
    $_SESSION['cart_names'] = [];
}

function cart_session_lines(): array
{
    $catalog = new Catalog();
    $names = cart_session_names();
    $lines = [];
    foreach (cart_session_raw() as $id => $qty) {
        $product = $catalog->getProductById((int)$id);
        if (!$product) {
            // Товар деактивирован/удалён: НЕ прячем позицию молча — показываем её
            // пользователю как недоступную, оформление такой корзины блокируется.
            $lines[] = cart_unavailable_line((int)$id, (int)$qty, (string)($names[(int)$id] ?? '')) + [
                'image_url' => '/images/no-image.png',
                'min_order_qty' => 1,
                'qty_step' => 1,
            ];
            continue;
        }
        cart_session_remember_name((int)$id, (string)$product['full_name']);
        $lines[] = cart_build_line($product, (int)$qty) + [
            'image_url' => $product['image_url'] ?? '/images/no-image.png',
            'min_order_qty' => (int)($product['min_order_qty'] ?? 1),
            'qty_step' => (int)($product['qty_step'] ?? 1),
        ];
    }
    return $lines;
}

function cart_session_count(): int
{
    return count(cart_session_raw());
}
