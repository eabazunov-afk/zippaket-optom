<?php
require_once __DIR__ . '/YooKassaGateway.php';

/**
 * Собрать платёжный шлюз из конфигурации.
 * Тестовый/боевой режим ЮKassa определяется парой shopId+secretKey
 * (у тестового магазина — свои), URL API один и тот же.
 */
function payment_gateway(): PaymentGateway
{
    $apiUrl = defined('YOOKASSA_API_URL') ? YOOKASSA_API_URL : 'https://api.yookassa.ru/v3';
    $vat = defined('YOOKASSA_VAT_CODE') ? (int)YOOKASSA_VAT_CODE : 1;
    return new YooKassaGateway(YOOKASSA_SHOP_ID, YOOKASSA_SECRET_KEY, $apiUrl, null, $vat);
}

/** Настроен ли шлюз (есть непустые креды). */
function payment_gateway_configured(): bool
{
    if (!defined('YOOKASSA_SHOP_ID') || !defined('YOOKASSA_SECRET_KEY')) {
        return false;
    }
    $id = YOOKASSA_SHOP_ID;
    $key = YOOKASSA_SECRET_KEY;
    if ($id === '' || $key === '') {
        return false;
    }
    // плейсхолдеры из шаблона не считаем настройкой
    return strpos($id, 'ВАШ_') !== 0 && strpos($key, 'ВАШ_') !== 0;
}
