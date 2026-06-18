<?php
/**
 * Чистый маппинг статуса платежа провайдера в целевой статус заказа.
 * Без БД и сети — легко тестируется.
 *
 * ЮKassa payment.status: pending | waiting_for_capture | succeeded | canceled
 * @return string|null  целевой статус заказа ('paid'|'canceled') или null, если переход не нужен
 */
function yookassa_target_order_status(string $providerStatus, bool $paid): ?string
{
    if ($providerStatus === 'succeeded' && $paid) {
        return 'paid';
    }
    if ($providerStatus === 'canceled') {
        return 'canceled';
    }
    // pending / waiting_for_capture — ждём, статус заказа не меняем
    return null;
}
