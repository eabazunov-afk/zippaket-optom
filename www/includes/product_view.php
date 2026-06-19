<?php
// Чистые функции форматирования товара для каталога/карточки. Без БД.
require_once __DIR__ . '/cart_quantity.php';

/**
 * Премиум-изображение товара по категории/цвету (вместо низкокачественных CGI image_url).
 * Слайдеры: матовый → eva.png, прозрачный → pvd.png. Грипперы → gripper.jpg.
 */
function pv_product_image(array $p): string
{
    $cat = (string)($p['category'] ?? '');
    $color = (string)($p['color'] ?? '');
    if (mb_stripos($cat, 'слайдер') !== false) {
        return (mb_stripos($color, 'мат') !== false) ? '/images/eva.png' : '/images/pvd.png';
    }
    return '/images/gripper.jpg';
}

function pv_format_price(float $price): string
{
    return number_format($price, 2, ',', ' ') . ' ₽';
}

function pv_format_size(?int $width, ?int $height): string
{
    if ($width === null && $height === null) {
        return '';
    }
    return ($width ?? 0) . '×' . ($height ?? 0) . ' мм';
}

function pv_stock_status(int $stock): array
{
    if ($stock > 0) {
        return [
            'in_stock'    => true,
            'label'       => 'В наличии',
            'count_label' => number_format($stock, 0, ',', ' ') . ' шт',
        ];
    }
    return ['in_stock' => false, 'label' => 'Под заказ', 'count_label' => ''];
}

function pv_default_qty(int $min, int $step): int
{
    return normalize_quantity($min, $min, $step);
}

function pv_pack_note(int $min, int $step): string
{
    $note = 'мин. ' . $min;
    if ($step > 1) {
        $note .= ' · кратно ' . $step;
    }
    return $note;
}
