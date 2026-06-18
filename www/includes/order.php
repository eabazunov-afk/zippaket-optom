<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cart_logic.php';

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
        return ['ok' => true, 'order_id' => $orderId, 'order_number' => $orderNo];
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        error_log('order_create error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'db_error'];
    }
}
