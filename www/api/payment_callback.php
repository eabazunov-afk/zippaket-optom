<?php
/**
 * Webhook ЮKassa: уведомление о смене статуса платежа.
 * Настроить в ЛК ЮKassa на событие payment.succeeded (и при желании payment.canceled),
 * URL: https://<домен>/api/payment_callback.php
 *
 * Безопасность (defense in depth):
 *   1) verifySignature — IP-источник из диапазонов ЮKassa;
 *   2) повторный getPayment — доверяем статусу из API, а не телу запроса.
 */
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/payment/payment_factory.php';

header('Content-Type: application/json; charset=utf-8');

function callback_done(int $code, string $result): void
{
    http_response_code($code);
    echo json_encode(['result' => $result], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    callback_done(405, 'method_not_allowed');
}
if (!payment_gateway_configured()) {
    error_log('payment_callback: шлюз не настроен');
    callback_done(503, 'gateway_not_configured');
}

$rawBody = file_get_contents('php://input') ?: '';
$gateway = payment_gateway();

if (!$gateway->verifySignature(['REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? ''], $rawBody)) {
    error_log('payment_callback: недоверенный источник ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    callback_done(403, 'forbidden');
}

$event = $gateway->parseCallback($_POST, $rawBody);
if ($event['payment_id'] === '') {
    callback_done(400, 'bad_request');
}

// Не доверяем статусу из тела — перезапрашиваем платёж у провайдера.
try {
    $payment = $gateway->getPayment($event['payment_id']);
} catch (Throwable $e) {
    error_log('payment_callback getPayment failed: ' . $e->getMessage());
    callback_done(502, 'provider_error'); // 5xx → ЮKassa повторит уведомление
}

$status = (string)($payment['status'] ?? '');
$paid = (bool)($payment['paid'] ?? false);

$result = order_apply_payment_status($event['payment_id'], $status, $paid);
if ($result === 'not_found') {
    error_log('payment_callback: заказ для payment ' . $event['payment_id'] . ' не найден');
    callback_done(404, 'order_not_found');
}

// applied / ignored / forbidden — приняли уведомление (200), повтор не нужен.
callback_done(200, $result);
