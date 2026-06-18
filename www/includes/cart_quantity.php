<?php
/**
 * Нормализует количество товара под минимум и кратность упаковке.
 * Округляет ВВЕРХ до ближайшего допустимого: min, min+step, min+2*step, ...
 */
function normalize_quantity(int $qty, int $min, int $step): int
{
    if ($step <= 0) {
        $step = 1;
    }
    if ($min <= 0) {
        $min = $step;
    }
    if ($qty <= $min) {
        return $min;
    }
    $over = $qty - $min;
    $remainder = $over % $step;
    if ($remainder === 0) {
        return $qty;
    }
    return $qty + ($step - $remainder);
}

/**
 * Проверяет, что количество допустимо без изменения.
 */
function is_valid_quantity(int $qty, int $min, int $step): bool
{
    if ($step <= 0) {
        $step = 1;
    }
    if ($min <= 0) {
        $min = $step;
    }
    return $qty >= $min && (($qty - $min) % $step) === 0;
}
