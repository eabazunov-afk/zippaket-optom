<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/catalog_functions.php';
require_once __DIR__ . '/cart_quantity.php';
require_once __DIR__ . '/cart_logic.php';

function cart_session_raw(): array
{
    return isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
}

function cart_session_add(int $productId, int $qty): void
{
    if ($productId <= 0) { return; }
    $cart = cart_session_raw();
    $cart[$productId] = ($cart[$productId] ?? 0) + max(1, $qty);
    $_SESSION['cart'] = $cart;
}

function cart_session_set(int $productId, int $qty): void
{
    $cart = cart_session_raw();
    if ($qty <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = $qty;
    }
    $_SESSION['cart'] = $cart;
}

function cart_session_remove(int $productId): void
{
    $cart = cart_session_raw();
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
}

function cart_session_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_session_lines(): array
{
    $catalog = new Catalog();
    $lines = [];
    foreach (cart_session_raw() as $id => $qty) {
        $product = $catalog->getProductById((int)$id);
        if (!$product) { continue; }
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
