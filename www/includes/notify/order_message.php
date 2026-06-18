<?php
/**
 * Чистое форматирование уведомлений о заказе (без сети и БД) — легко тестируется.
 * На вход: $order — строка из orders; $items — строки order_items
 * (name_snapshot, price_snapshot, qty, line_total).
 */

/** Денежный формат «1 500,00 ₽». */
function notify_money(float $v): string
{
    return number_format($v, 2, ',', ' ') . ' ₽';
}

/** Человекочитаемые подписи способов. */
function notify_label(string $group, string $key): string
{
    $map = [
        'customer_type' => ['individual' => 'Физлицо', 'company' => 'Юрлицо'],
        'delivery'      => ['pickup' => 'Самовывоз', 'courier' => 'Курьер', 'tk' => 'Транспортная компания'],
        'payment'       => ['online' => 'Картой онлайн', 'invoice' => 'По счёту'],
        'status'        => [
            'pending_payment' => 'Ожидает оплаты', 'paid' => 'Оплачен',
            'processing' => 'В работе', 'shipped' => 'Отгружен',
            'done' => 'Выполнен', 'canceled' => 'Отменён', 'new' => 'Новый',
        ],
    ];
    return $map[$group][$key] ?? $key;
}

/** Список позиций в виде строк «• Название × qty = line_total». */
function notify_items_text(array $items): string
{
    $out = [];
    foreach ($items as $it) {
        $out[] = '• ' . ($it['name_snapshot'] ?? $it['name'] ?? '?')
            . ' × ' . (int)($it['qty'] ?? 0)
            . ' = ' . notify_money((float)($it['line_total'] ?? 0));
    }
    return implode("\n", $out);
}

/** Текст уведомления для Telegram (новый заказ или смена статуса). */
function format_order_telegram(array $order, array $items, string $event = 'new'): string
{
    $head = $event === 'paid'
        ? '💰 Заказ оплачен'
        : '🛒 Новый заказ';

    $lines = [
        $head . ' ' . ($order['order_number'] ?? ''),
        'Статус: ' . notify_label('status', (string)($order['status'] ?? '')),
        'Клиент: ' . ($order['customer_name'] ?? '') . ' (' . notify_label('customer_type', (string)($order['customer_type'] ?? '')) . ')',
        'Телефон: ' . ($order['phone'] ?? ''),
    ];
    if (!empty($order['email'])) {
        $lines[] = 'Email: ' . $order['email'];
    }
    if (($order['customer_type'] ?? '') === 'company' && !empty($order['company_name'])) {
        $lines[] = 'Организация: ' . $order['company_name'] . (!empty($order['inn']) ? ', ИНН ' . $order['inn'] : '');
    }
    $lines[] = 'Доставка: ' . notify_label('delivery', (string)($order['delivery_method'] ?? ''));
    if (!empty($order['delivery_address'])) {
        $lines[] = 'Адрес: ' . $order['delivery_address'];
    }
    $lines[] = 'Оплата: ' . notify_label('payment', (string)($order['payment_method'] ?? ''));
    if (!empty($order['comment'])) {
        $lines[] = 'Комментарий: ' . $order['comment'];
    }
    $lines[] = '';
    $lines[] = notify_items_text($items);
    $lines[] = '';
    $lines[] = 'Итого: ' . notify_money((float)($order['total'] ?? 0));

    return implode("\n", $lines);
}

/** Email: тема и HTML-тело. @return array{subject:string, body:string} */
function format_order_email(array $order, array $items, string $event = 'new'): array
{
    $subjectHead = $event === 'paid' ? 'Заказ оплачен' : 'Новый заказ';
    $subject = $subjectHead . ' ' . ($order['order_number'] ?? '') . ' — ' . notify_money((float)($order['total'] ?? 0));

    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr>'
            . '<td>' . htmlspecialchars((string)($it['name_snapshot'] ?? $it['name'] ?? '')) . '</td>'
            . '<td align="center">' . (int)($it['qty'] ?? 0) . '</td>'
            . '<td align="right">' . htmlspecialchars(notify_money((float)($it['line_total'] ?? 0))) . '</td>'
            . '</tr>';
    }

    $esc = fn($k) => htmlspecialchars((string)($order[$k] ?? ''));
    $body = '<h2>' . htmlspecialchars($subjectHead) . ' ' . $esc('order_number') . '</h2>'
        . '<p><b>Статус:</b> ' . htmlspecialchars(notify_label('status', (string)($order['status'] ?? ''))) . '<br>'
        . '<b>Клиент:</b> ' . $esc('customer_name') . ' (' . htmlspecialchars(notify_label('customer_type', (string)($order['customer_type'] ?? ''))) . ')<br>'
        . '<b>Телефон:</b> ' . $esc('phone') . '<br>'
        . (!empty($order['email']) ? '<b>Email:</b> ' . $esc('email') . '<br>' : '')
        . '<b>Доставка:</b> ' . htmlspecialchars(notify_label('delivery', (string)($order['delivery_method'] ?? ''))) . '<br>'
        . '<b>Оплата:</b> ' . htmlspecialchars(notify_label('payment', (string)($order['payment_method'] ?? ''))) . '</p>'
        . '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse">'
        . '<tr><th>Товар</th><th>Кол-во</th><th>Сумма</th></tr>' . $rows
        . '<tr><td colspan="2" align="right"><b>Итого</b></td><td align="right"><b>' . htmlspecialchars(notify_money((float)($order['total'] ?? 0))) . '</b></td></tr>'
        . '</table>';

    return ['subject' => $subject, 'body' => $body];
}

/** Маппинг заказа в формат sendToAmoCRM($data). */
function order_to_amocrm_data(array $order, array $items): array
{
    $msgLines = ['Заказ ' . ($order['order_number'] ?? '') . ', сумма ' . notify_money((float)($order['total'] ?? 0))];
    $msgLines[] = 'Доставка: ' . notify_label('delivery', (string)($order['delivery_method'] ?? ''))
        . (!empty($order['delivery_address']) ? ' (' . $order['delivery_address'] . ')' : '');
    $msgLines[] = 'Оплата: ' . notify_label('payment', (string)($order['payment_method'] ?? ''));
    $msgLines[] = notify_items_text($items);
    if (!empty($order['comment'])) {
        $msgLines[] = 'Комментарий: ' . $order['comment'];
    }

    return [
        'name'    => (string)($order['customer_name'] ?? ''),
        'phone'   => (string)($order['phone'] ?? ''),
        'email'   => (string)($order['email'] ?? ''),
        'type'    => 'order',
        'source'  => 'website-order',
        'message' => implode("\n", $msgLines),
        'parameters' => [
            'order_number' => (string)($order['order_number'] ?? ''),
            'total' => (string)($order['total'] ?? ''),
            'company_name' => (string)($order['company_name'] ?? ''),
            'inn' => (string)($order['inn'] ?? ''),
        ],
    ];
}
