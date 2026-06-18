<?php
/**
 * Оркестратор уведомлений о заказе: Telegram (админ), Email (админ/клиент), amoCRM-лид.
 *
 * Принцип: best-effort. Каждый канал изолирован try/catch — сбой одного не влияет на
 * другие и НИКОГДА не ломает оформление заказа/обработку webhook. Результат по каналам
 * возвращается для логов/тестов.
 */
require_once __DIR__ . '/order_message.php';
require_once __DIR__ . '/telegram_notify.php';
require_once __DIR__ . '/../config.php'; // sendEmail(), ADMIN_EMAIL

/** Безопасно выполнить канал: вернуть bool, залогировать исключение. */
function notify_channel(string $name, callable $fn): bool
{
    try {
        return (bool)$fn();
    } catch (Throwable $e) {
        error_log("notify[$name] error: " . $e->getMessage());
        return false;
    }
}

/** Уведомление администратору в Telegram. */
function notify_telegram(array $order, array $items, string $event): bool
{
    return notify_channel('telegram', function () use ($order, $items, $event) {
        if (!telegram_configured()) {
            return false;
        }
        return telegram_send(format_order_telegram($order, $items, $event));
    });
}

/** Email-уведомление администратору. */
function notify_email_admin(array $order, array $items, string $event): bool
{
    return notify_channel('email_admin', function () use ($order, $items, $event) {
        if (!defined('ADMIN_EMAIL') || ADMIN_EMAIL === '') {
            return false;
        }
        $mail = format_order_email($order, $items, $event);
        return sendEmail(ADMIN_EMAIL, $mail['subject'], $mail['body']);
    });
}

/** Email-подтверждение клиенту (если оставил email). */
function notify_email_customer(array $order, array $items, string $event): bool
{
    return notify_channel('email_customer', function () use ($order, $items, $event) {
        $to = (string)($order['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $mail = format_order_email($order, $items, $event);
        return sendEmail($to, $mail['subject'], $mail['body']);
    });
}

/** Создать лид в amoCRM из заказа. */
function notify_amocrm(array $order, array $items): bool
{
    return notify_channel('amocrm', function () use ($order, $items) {
        $amo = __DIR__ . '/../amocrm.php';
        if (!is_file($amo)) {
            return false;
        }
        require_once $amo;
        if (!function_exists('sendToAmoCRM')) {
            return false;
        }
        $result = sendToAmoCRM(order_to_amocrm_data($order, $items));
        return $result !== false && $result !== null;
    });
}

/**
 * Новый заказ: уведомляем команду (Telegram + Email + amoCRM-лид).
 * @return array<string,bool>
 */
function notify_new_order(array $order, array $items): array
{
    return [
        'telegram' => notify_telegram($order, $items, 'new'),
        'email'    => notify_email_admin($order, $items, 'new'),
        'amocrm'   => notify_amocrm($order, $items),
    ];
}

/**
 * Заказ оплачен: уведомляем команду и подтверждаем клиенту.
 * @return array<string,bool>
 */
function notify_order_paid(array $order, array $items): array
{
    return [
        'telegram'        => notify_telegram($order, $items, 'paid'),
        'email'           => notify_email_admin($order, $items, 'paid'),
        'email_customer'  => notify_email_customer($order, $items, 'paid'),
    ];
}
