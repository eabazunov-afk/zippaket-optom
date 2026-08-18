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
        // Непредсказуемый токен доступа к заказу/счёту (защита от IDOR по номеру).
        $accessToken = bin2hex(random_bytes(16));

        $stmt = $db->prepare("INSERT INTO orders
            (order_number, access_token, status, customer_type, customer_name, phone, email,
             company_name, inn, kpp, legal_address, needs_invoice,
             delivery_method, delivery_address, comment, payment_method,
             items_total, total)
            VALUES
            (:order_number, :access_token, 'pending_payment', :customer_type, :customer_name, :phone, :email,
             :company_name, :inn, :kpp, :legal_address, :needs_invoice,
             :delivery_method, :delivery_address, :comment, :payment_method,
             :items_total, :total)");
        $params = [
            ':order_number' => '', // проставляется в цикле ниже
            ':access_token' => $accessToken,
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
        ];

        // Гонка номеров: при параллельных заказах COUNT+1 у двух запросов совпадает
        // → второй INSERT падает дубликатом uq_order_number. В InnoDB duplicate-key
        // откатывает лишь оператор (не транзакцию), поэтому берём следующий seq и
        // повторяем. order_number — единственный UNIQUE на orders, значит 1062 здесь
        // всегда именно о номере.
        $orderNo = '';
        for ($attempt = 0; ; $attempt++) {
            $orderNo = order_number($seq + $attempt);
            $params[':order_number'] = $orderNo;
            try {
                $stmt->execute($params);
                break;
            } catch (PDOException $e) {
                if ($attempt < 25 && (int)($e->errorInfo[1] ?? 0) === 1062) {
                    continue; // номер занят параллельным заказом — пробуем следующий
                }
                throw $e;
            }
        }
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
        return ['ok' => true, 'order_id' => $orderId, 'order_number' => $orderNo, 'access_token' => $accessToken, 'total' => $totals['items_total']];
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

/** Проверка токена доступа к заказу (защита от IDOR), constant-time. */
function order_token_valid(?array $order, string $token): bool
{
    if ($order === null || empty($order['access_token']) || $token === '') {
        return false;
    }
    return hash_equals((string)$order['access_token'], $token);
}

/** Позиции заказа (order_items). */
function order_items_get(int $orderId): array
{
    $stmt = getDbConnection()->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll() ?: [];
}

/** Список заказов для админки (новые сверху). */
function orders_list(int $limit = 200): array
{
    $stmt = getDbConnection()->prepare("SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

/**
 * Ручная смена статуса заказа администратором (с проверкой матрицы переходов).
 * @return array{ok:bool, error?:string}
 */
function order_admin_set_status(int $orderId, string $newStatus): array
{
    if (!in_array($newStatus, order_statuses(), true)) {
        return ['ok' => false, 'error' => 'bad_status'];
    }
    $order = order_get($orderId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if ($order['status'] === $newStatus) {
        return ['ok' => true];
    }
    if (!can_transition($order['status'], $newStatus)) {
        return ['ok' => false, 'error' => 'forbidden_transition'];
    }
    $stmt = getDbConnection()->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);
    return ['ok' => true];
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
    return order_apply_payment_status_db(getDbConnection(), $paymentId, $providerStatus, $paid);
}

/**
 * Та же логика с явно переданным подключением — точка расширения для тестов
 * (фейковый PDO вместо реальной БД). Контракт возвращаемых значений см. выше.
 */
function order_apply_payment_status_db(PDO $db, string $paymentId, string $providerStatus, bool $paid): string
{
    $sel = $db->prepare("SELECT id, status FROM orders WHERE payment_id = ?");
    $sel->execute([$paymentId]);
    $order = $sel->fetch();
    if (!$order) {
        return 'not_found';
    }
    $orderId = (int)$order['id'];
    $current = (string)$order['status'];

    // payment_status пишем всегда (для аудита), даже если статус заказа не меняется.
    $target = yookassa_target_order_status($providerStatus, $paid);
    if ($target === null) {
        order_write_payment_status($db, $orderId, $providerStatus);
        return 'ignored';
    }

    if ($current === $target) {
        order_write_payment_status($db, $orderId, $providerStatus);
        return 'noop'; // уже в целевом статусе — идемпотентно, повторно не уведомляем
    }
    if (!can_transition($current, $target)) {
        order_write_payment_status($db, $orderId, $providerStatus);
        return 'forbidden';
    }

    // Атомарный переход: условие по прочитанному статусу прямо в WHERE.
    // ЮKassa ретраит уведомление при таймауте, и два параллельных webhook
    // видят один и тот же pending_payment. Без условия оба сделали бы UPDATE
    // и оба вернули applied → двойное уведомление клиенту и в Telegram.
    // Строку меняет только первый; второй получает rowCount()=0 → noop.
    $upd = $db->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ? AND status = ?");
    $upd->execute([$target, $providerStatus, $orderId, $current]);
    if ($upd->rowCount() === 0) {
        // Статус успел измениться между SELECT и UPDATE — гонку выиграл другой webhook.
        order_write_payment_status($db, $orderId, $providerStatus);
        return 'noop';
    }
    return 'applied';
}

/** Записать payment_status заказа без смены orders.status (аудит webhook'ов). */
function order_write_payment_status(PDO $db, int $orderId, string $providerStatus): void
{
    $upd = $db->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
    $upd->execute([$providerStatus, $orderId]);
}

/**
 * Совпадают ли денежные суммы с точностью до копейки.
 * amount.value от ЮKassa приходит строкой «1234.00», orders.total — строкой
 * DECIMAL («1234.0000» и т.п.), поэтому сравниваем нормализованные копейки,
 * а не строки и не float напрямую.
 */
function order_amount_equals($a, $b): bool
{
    return (int)round((float)$a * 100) === (int)round((float)$b * 100);
}
