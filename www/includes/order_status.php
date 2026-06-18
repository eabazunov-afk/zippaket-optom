<?php
/** Все валидные статусы заказа. */
function order_statuses(): array
{
    return ['new', 'pending_payment', 'paid', 'processing', 'shipped', 'done', 'canceled'];
}

/** Разрешён ли переход между статусами. */
function can_transition(string $from, string $to): bool
{
    $allowed = [
        'new'             => ['pending_payment', 'canceled'],
        'pending_payment' => ['paid', 'canceled'],
        'paid'            => ['processing', 'canceled'],
        'processing'      => ['shipped', 'canceled'],
        'shipped'         => ['done'],
        'done'            => [],
        'canceled'        => [],
    ];
    return isset($allowed[$from]) && in_array($to, $allowed[$from], true);
}
