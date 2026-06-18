<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cart_logic.php';
require_once __DIR__ . '/order_status.php';
require_once __DIR__ . '/payment/payment_status_map.php';

function order_create(array $checkoutData, array $lines): array
{
    if (empty($lines)) {
        return ['ok' => false, 'error' => 'empty_cart'];
    }
    $totals = cart_totals($lines);
    $db = getDbConnection();
    try {
        $db->beginTransaction();

        $countStmt = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
        $seq = (int)$countStmt->fetchColumn() + 1;
        $orderNo = order_number($seq);

        $stmt = $db->prepare("INSERT INTO orders
            (order_number, status, customer_type, customer_name, phone, email,
             company_name, inn, kpp, legal_address, needs_invoice,
             delivery_method, delivery_address, comment, payment_method,
             items_total, total)
            VALUES
            (:order_number, 'pending_payment', :customer_type, :customer_name, :phone, :email,
             :company_name, :inn, :kpp, :legal_address, :needs_invoice,
             :delivery_method, :delivery_address, :comment, :payment_method,
             :items_total, :total)");
        $stmt->execute([
            ':order_number' => $orderNo,
            ':customer_type' => $checkoutData['customer_type'],
            ':customer_name' => $checkoutData['customer_name'],
            ':phone' => $checkoutData['phone'],
            ':email' => $checkoutData['email'] !== '' ? $checkoutData['email'] : null,
            ':company_name' => $checkoutData['company_name'],
            ':inn' => $checkoutData['inn'],
            ':kpp' => $checkoutData['kpp'],
            ':legal_address' => $checkoutData['legal_address'],
            ':needs_invoice' => $checkoutData['needs_invoice'],
            ':delivery_method' => $checkoutData['delivery_method'],
            ':delivery_address' => $checkoutData['delivery_address'],
            ':comment' => $checkoutData['comment'],
            ':payment_method' => $checkoutData['payment_method'],
            ':items_total' => $totals['items_total'],
            ':total' => $totals['items_total'],
        ]);
        $orderId = (int)$db->lastInsertId();

        $itemStmt = $db->prepare("INSERT INTO order_items
            (order_id, product_id, name_snapshot, price_snapshot, qty, line_total)
            VALUES (:order_id, :product_id, :name, :price, :qty, :line_total)");
        foreach ($lines as $l) {
            $itemStmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $l['product_id'],
                ':name' => $l['name'],
                ':price' => $l['price'],
                ':qty' => $l['qty'],
                ':line_total' => $l['line_total'],
            ]);
        }

        $db->commit();
        return ['ok' => true, 'order_id' => $orderId, 'order_number' => $orderNo, 'total' => $totals['items_total']];
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log('order_create error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'db_error'];
    }
}

/** Заказ по id. */
function order_get(int $orderId): ?array
{
    $stmt = getDbConnection()->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Заказ по номеру. */
function order_get_by_number(string $orderNumber): ?array
{
    $stmt = getDbConnection()->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Позиции заказа (order_items). */
function order_items_get(int $orderId): array
{
    $stmt = getDbConnection()->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll() ?: [];
}

/** Заказ по payment_id провайдера. */
function order_get_by_payment_id(string $paymentId): ?array
{
    $stmt = getDbConnection()->prepare("SELECT * FROM orders WHERE payment_id = ?");
    $stmt->execute([$paymentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Привязать платёж к заказу (после createPayment). */
function order_set_payment(int $orderId, string $paymentId): void
{
    $stmt = getDbConnection()->prepare(
        "UPDATE orders SET payment_id = ?, payment_status = 'pending' WHERE id = ?"
    );
    $stmt->execute([$paymentId, $orderId]);
}

/**
 * Применить статус платежа провайдера к заказу (вызывается из webhook).
 * Меняет orders.status только при разрешённом переходе (idempotent).
 * @return string applied|noop|ignored|forbidden|not_found
 *   applied  — статус заказа реально изменён (первый раз) → можно слать уведомление;
 *   noop     — заказ уже в целевом статусе (повтор webhook) → уведомление НЕ слать;
 *   ignored  — статус платежа промежуточный, заказ не трогаем;
 *   forbidden— переход запрещён матрицей статусов;
 *   not_found— заказ по payment_id не найден.
 */
function order_apply_payment_status(string $paymentId, string $providerStatus, bool $paid): string
{
    $db = getDbConnection();
    $order = order_get_by_payment_id($paymentId);
    if ($order === null) {
        return 'not_found';
    }

    // payment_status пишем всегда (для аудита), даже если статус заказа не меняется.
    $target = yookassa_target_order_status($providerStatus, $paid);
    if ($target === null) {
        $upd = $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $upd->execute([$providerStatus, (int)$order['id']]);
        return 'ignored';
    }

    if ($order['status'] === $target) {
        $upd = $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $upd->execute([$providerStatus, (int)$order['id']]);
        return 'noop'; // уже в целевом статусе — идемпотентно, повторно не уведомляем
    }
    if (!can_transition($order['status'], $target)) {
        $upd = $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $upd->execute([$providerStatus, (int)$order['id']]);
        return 'forbidden';
    }

    $upd = $db->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
    $upd->execute([$target, $providerStatus, (int)$order['id']]);
    return 'applied';
}
