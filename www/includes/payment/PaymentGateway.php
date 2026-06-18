<?php
/**
 * Контракт платёжного шлюза. Реализации: YooKassaGateway (План 4) и др.
 * Заказы не привязаны к конкретному провайдеру.
 */
interface PaymentGateway
{
    /**
     * @param array  $order     заказ из БД (id, order_number, total, items, customer ...)
     * @param string $returnUrl URL возврата покупателя после оплаты
     * @return array{payment_id:string, confirmation_url:string}
     */
    public function createPayment(array $order, string $returnUrl): array;

    /**
     * @return array{payment_id:string, status:string, paid:bool}
     */
    public function parseCallback(array $request, string $rawBody): array;

    public function verifySignature(array $headers, string $rawBody): bool;
}
