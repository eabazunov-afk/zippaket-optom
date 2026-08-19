<?php
use PHPUnit\Framework\TestCase;

// order.php тянет config.php (только определения функций — коннект создаётся
// лениво в getDbConnection и здесь не вызывается): вся работа с БД идёт через
// order_create_db с фейковым PDO.
require_once __DIR__ . '/../includes/order.php';

/**
 * Деньги заказа: суммы считает сервер, снапшот пишется из строк корзины,
 * гонка номеров ретраится, сбой позиции откатывает транзакцию.
 */
class OrderCreateTest extends TestCase
{
    /** Записанное фейковой БД состояние: что вставили, что закоммитили. */
    private object $log;

    protected function setUp(): void
    {
        $this->log = (object)[
            'orders'       => [],   // параметры каждого успешного INSERT INTO orders
            'orderTries'   => [],   // order_number каждой попытки, включая упавшие
            'items'        => [],   // параметры INSERT INTO order_items
            'began'        => 0,
            'committed'    => 0,
            'rolledBack'   => 0,
            'ordersToday'  => 0,    // что вернёт COUNT(*) за сутки
            'takenNumbers' => [],   // номера, занятые «параллельным» заказом → 1062
            'failItemAt'   => null, // на какой по счёту позиции упасть (0-based)
            'lastInsertId' => 0,
        ];
    }

    /** Товар для cart_build_line: цена и шаг как у реального SKU. */
    private function product(array $over = []): array
    {
        return array_merge([
            'id' => 1, 'full_name' => 'Пакет ZIP-LOCK 20×15', 'price_rub' => '94.66',
            'min_order_qty' => 100, 'qty_step' => 50,
        ], $over);
    }

    /** Данные формы оформления. Денежных полей здесь нет и быть не должно. */
    private function checkoutData(array $over = []): array
    {
        return array_merge([
            'customer_type' => 'individual',
            'customer_name' => 'Иванов Иван',
            'phone' => '+7 900 000-00-00',
            'email' => 'i@example.com',
            'company_name' => null,
            'inn' => null,
            'kpp' => null,
            'legal_address' => null,
            'needs_invoice' => 0,
            'delivery_method' => 'pickup',
            'delivery_address' => null,
            'comment' => '',
            'payment_method' => 'online',
        ], $over);
    }

    /** Ошибка дубликата уникального ключа так, как её отдаёт PDO/MySQL. */
    private function duplicateKeyError(string $number): PDOException
    {
        $e = new PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '$number' for key 'orders.uq_order_number'"
        );
        $e->errorInfo = ['23000', 1062, "Duplicate entry '$number' for key 'orders.uq_order_number'"];
        return $e;
    }

    /**
     * Мини-фейк БД: транзакция + три запроса из order_create_db
     * (COUNT заказов за сутки, INSERT INTO orders, INSERT INTO order_items).
     * Всё записанное складывается в $this->log — после вызова можно проверить,
     * что именно ушло в БД.
     */
    private function fakeDb(): PDO
    {
        $log = $this->log;
        $tx = (object)['open' => false];

        $countStmt = $this->createMock(PDOStatement::class);
        $countStmt->method('fetchColumn')->willReturnCallback(fn() => $log->ordersToday);

        $orderStmt = $this->createMock(PDOStatement::class);
        $orderStmt->method('execute')->willReturnCallback(
            function (?array $params = null) use ($log): bool {
                $number = (string)($params[':order_number'] ?? '');
                $log->orderTries[] = $number;
                if (in_array($number, $log->takenNumbers, true)) {
                    throw $this->duplicateKeyError($number);
                }
                $log->orders[] = $params;
                $log->lastInsertId = count($log->orders) + 100;
                return true;
            }
        );

        $itemStmt = $this->createMock(PDOStatement::class);
        $itemStmt->method('execute')->willReturnCallback(
            function (?array $params = null) use ($log): bool {
                if ($log->failItemAt !== null && count($log->items) === $log->failItemAt) {
                    throw new PDOException('SQLSTATE[HY000]: General error: 1366 Incorrect decimal value');
                }
                $log->items[] = $params;
                return true;
            }
        );

        $db = $this->createMock(PDO::class);
        $db->method('query')->willReturnCallback(function (string $sql) use ($countStmt) {
            $this->assertStringContainsString('COUNT(*)', $sql);
            // Индексируемый полуинтервал вместо DATE(created_at) = CURDATE().
            $this->assertStringNotContainsString('DATE(created_at)', $sql);
            return $countStmt;
        });
        $db->method('prepare')->willReturnCallback(
            function (string $sql) use ($orderStmt, $itemStmt) {
                return str_contains($sql, 'INSERT INTO order_items') ? $itemStmt : $orderStmt;
            }
        );
        $db->method('beginTransaction')->willReturnCallback(function () use ($log, $tx): bool {
            $log->began++;
            $tx->open = true;
            return true;
        });
        $db->method('inTransaction')->willReturnCallback(fn(): bool => $tx->open);
        $db->method('commit')->willReturnCallback(function () use ($log, $tx): bool {
            $log->committed++;
            $tx->open = false;
            return true;
        });
        $db->method('rollBack')->willReturnCallback(function () use ($log, $tx): bool {
            $log->rolledBack++;
            $tx->open = false;
            return true;
        });
        $db->method('lastInsertId')->willReturnCallback(fn() => (string)$log->lastInsertId);

        return $db;
    }

    // --- Успешное создание -------------------------------------------------

    public function testTotalsAreComputedFromLinesNotFromClient(): void
    {
        $lines = [
            cart_build_line($this->product(), 150),                              // 150 × 94.66
            cart_build_line($this->product(['id' => 2, 'full_name' => 'Гриппер 10×15',
                'price_rub' => '10.00', 'min_order_qty' => 1, 'qty_step' => 1]), 100),
        ];
        $expected = round(150 * 94.66 + 100 * 10.0, 2);

        // Клиент «подсказывает» свои суммы — они не должны попасть в заказ.
        $data = $this->checkoutData(['items_total' => 1.0, 'total' => 1.0]);

        $res = order_create_db($this->fakeDb(), $data, $lines);

        $this->assertTrue($res['ok']);
        $this->assertSame($expected, $res['total']);
        $this->assertCount(1, $this->log->orders);
        $order = $this->log->orders[0];
        $this->assertSame($expected, $order[':items_total']);
        $this->assertSame($expected, $order[':total']);
        $this->assertSame(1, $this->log->committed);
        $this->assertSame(0, $this->log->rolledBack);
        $this->assertSame(101, $res['order_id']); // lastInsertId фейка
    }

    public function testOrderItemsGetPriceAndNameSnapshot(): void
    {
        $lines = [
            cart_build_line($this->product(), 150),
            cart_build_line($this->product(['id' => 2, 'full_name' => 'Гриппер 10×15',
                'price_rub' => '10.00', 'min_order_qty' => 1, 'qty_step' => 1]), 100),
        ];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertTrue($res['ok']);
        $this->assertCount(2, $this->log->items);
        foreach ($this->log->items as $i => $item) {
            $this->assertSame($lines[$i]['product_id'], $item[':product_id']);
            $this->assertSame($lines[$i]['name'], $item[':name']);       // name_snapshot
            $this->assertSame($lines[$i]['price'], $item[':price']);     // price_snapshot
            $this->assertSame($lines[$i]['qty'], $item[':qty']);
            $this->assertSame($lines[$i]['line_total'], $item[':line_total']);
            $this->assertSame(101, $item[':order_id']);
        }
    }

    public function testSnapshotKeepsWholesalePriceNotBasePrice(): void
    {
        // Количество попадает в оптовую ступень → в снапшот идёт цена со скидкой,
        // а не price_rub из карточки товара.
        $tiers = [['label' => 'Опт от 300к', 'mult' => 0.8, 'min_qty' => 300000]];
        $line = cart_build_line($this->product(['min_order_qty' => 1, 'qty_step' => 1]), 300000, $tiers);
        $this->assertSame(round(94.66 * 0.8, 2), $line['price']);

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), [$line]);

        $this->assertTrue($res['ok']);
        $this->assertSame(round(94.66 * 0.8, 2), $this->log->items[0][':price']);
        $this->assertNotSame(94.66, $this->log->items[0][':price']);
        $this->assertSame($line['line_total'], $this->log->orders[0][':items_total']);
    }

    public function testOrderNumberUsesTodaysSequence(): void
    {
        $this->log->ordersToday = 41; // сегодня уже 41 заказ → следующий 0042
        $lines = [cart_build_line($this->product(), 150)];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertTrue($res['ok']);
        $this->assertSame(order_number(42), $res['order_number']);
        $this->assertSame($res['order_number'], $this->log->orders[0][':order_number']);
    }

    // --- Ретрай при дубликате номера --------------------------------------

    public function testDuplicateOrderNumberIsRetriedWithNextSequence(): void
    {
        // Параллельный заказ уже занял номер, который посчитал COUNT(*)+1.
        $this->log->takenNumbers = [order_number(1)];
        $lines = [cart_build_line($this->product(), 150)];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertTrue($res['ok'], 'дубликат номера не должен ронять заказ');
        $this->assertSame(order_number(2), $res['order_number']);
        $this->assertSame([order_number(1), order_number(2)], $this->log->orderTries);
        $this->assertSame(1, $this->log->committed);
        $this->assertSame(0, $this->log->rolledBack);
    }

    public function testSeveralTakenNumbersAreSkipped(): void
    {
        $this->log->takenNumbers = [order_number(1), order_number(2), order_number(3)];
        $lines = [cart_build_line($this->product(), 150)];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertTrue($res['ok']);
        $this->assertSame(order_number(4), $res['order_number']);
        $this->assertCount(4, $this->log->orderTries);
    }

    public function testRetryGivesUpAfterLimitAndRollsBack(): void
    {
        // Патология (не гонка, а, например, битый номер): бесконечно не крутимся.
        for ($i = 1; $i <= 40; $i++) {
            $this->log->takenNumbers[] = order_number($i);
        }
        $lines = [cart_build_line($this->product(), 150)];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertFalse($res['ok']);
        $this->assertSame('db_error', $res['error']);
        $this->assertSame(26, count($this->log->orderTries)); // attempt 0..25
        $this->assertSame(1, $this->log->rolledBack);
        $this->assertSame(0, $this->log->committed);
    }

    public function testNonDuplicateErrorIsNotRetried(): void
    {
        $orderStmt = $this->createMock(PDOStatement::class);
        $log = $this->log;
        $orderStmt->method('execute')->willReturnCallback(function () use ($log): bool {
            $log->orderTries[] = 'x';
            $e = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
            $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
            throw $e;
        });
        $countStmt = $this->createMock(PDOStatement::class);
        $countStmt->method('fetchColumn')->willReturn(0);
        $tx = (object)['open' => false];
        $db = $this->createMock(PDO::class);
        $db->method('query')->willReturn($countStmt);
        $db->method('prepare')->willReturn($orderStmt);
        $db->method('beginTransaction')->willReturnCallback(function () use ($tx): bool { $tx->open = true; return true; });
        $db->method('inTransaction')->willReturnCallback(fn(): bool => $tx->open);
        $db->method('rollBack')->willReturnCallback(function () use ($log, $tx): bool {
            $log->rolledBack++; $tx->open = false; return true;
        });

        $res = order_create_db($db, $this->checkoutData(), [cart_build_line($this->product(), 150)]);

        $this->assertFalse($res['ok']);
        $this->assertSame('db_error', $res['error']);
        $this->assertCount(1, $this->log->orderTries); // без ретраев
        $this->assertSame(1, $this->log->rolledBack);
    }

    // --- Откат транзакции --------------------------------------------------

    public function testFailedItemInsertRollsBackWholeOrder(): void
    {
        $this->log->failItemAt = 1; // первая позиция прошла, вторая упала
        $lines = [
            cart_build_line($this->product(), 150),
            cart_build_line($this->product(['id' => 2, 'full_name' => 'Гриппер 10×15',
                'price_rub' => '10.00', 'min_order_qty' => 1, 'qty_step' => 1]), 100),
        ];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertFalse($res['ok']);
        $this->assertSame('db_error', $res['error']);
        $this->assertArrayNotHasKey('order_id', $res);
        $this->assertSame(1, $this->log->began);
        $this->assertSame(1, $this->log->rolledBack, 'транзакция должна быть откачена');
        $this->assertSame(0, $this->log->committed, 'полузаказ коммитить нельзя');
    }

    public function testFailedFirstItemInsertRollsBack(): void
    {
        $this->log->failItemAt = 0;
        $res = order_create_db($this->fakeDb(), $this->checkoutData(), [cart_build_line($this->product(), 150)]);

        $this->assertFalse($res['ok']);
        $this->assertSame(1, $this->log->rolledBack);
        $this->assertSame(0, $this->log->committed);
        $this->assertSame([], $this->log->items);
    }

    // --- Валидация: заказ не создаётся ------------------------------------

    public function testEmptyCartIsRejectedBeforeAnyDbWork(): void
    {
        $db = $this->fakeDb();
        $res = order_create_db($db, $this->checkoutData(), []);

        $this->assertFalse($res['ok']);
        $this->assertSame('empty_cart', $res['error']);
        $this->assertSame(0, $this->log->began);
        $this->assertSame([], $this->log->orders);
    }

    public function testLineWithoutPriceIsRejectedAsInvalidCart(): void
    {
        // «Цена по запросу»: price_rub = NULL → available = false.
        $lines = [cart_build_line($this->product(['price_rub' => null]), 150)];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertFalse($res['ok']);
        $this->assertSame('invalid_cart', $res['error']);
        $this->assertNotEmpty($res['issues']);
        $this->assertSame(0, $this->log->began, 'транзакция не должна открываться');
        $this->assertSame([], $this->log->orders);
        $this->assertSame([], $this->log->items);
    }

    public function testGoneProductIsRejectedAsInvalidCart(): void
    {
        $lines = [cart_unavailable_line(7, 100, 'Снятый с продажи пакет')];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertFalse($res['ok']);
        $this->assertSame('invalid_cart', $res['error']);
        $this->assertSame(0, $this->log->began);
    }

    public function testZeroTotalIsRejectedAsInvalidCart(): void
    {
        // Позиция помечена доступной (цена есть), но сумма строки нулевая —
        // например, корзина пришла из внешнего источника с qty = 0.
        // Последний рубеж перед INSERT — проверка суммы, а не только available.
        $line = array_merge(cart_build_line($this->product(), 150), [
            'qty' => 0, 'line_total' => 0.0,
        ]);
        $this->assertTrue($line['available']);
        $this->assertSame(0.0, cart_totals([$line])['items_total']);

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), [$line]);

        $this->assertFalse($res['ok']);
        $this->assertSame('invalid_cart', $res['error']);
        $this->assertSame(0, $this->log->began);
        $this->assertSame([], $this->log->orders);
    }

    public function testOneBadLineBlocksWholeOrder(): void
    {
        $lines = [
            cart_build_line($this->product(), 150),                        // нормальная
            cart_build_line($this->product(['id' => 2, 'price_rub' => null]), 100), // без цены
        ];

        $res = order_create_db($this->fakeDb(), $this->checkoutData(), $lines);

        $this->assertFalse($res['ok']);
        $this->assertSame('invalid_cart', $res['error']);
        $this->assertSame([], $this->log->orders);
    }

    // --- access_token ------------------------------------------------------

    public function testAccessTokenIsGeneratedAndStored(): void
    {
        $res = order_create_db($this->fakeDb(), $this->checkoutData(), [cart_build_line($this->product(), 150)]);

        $this->assertTrue($res['ok']);
        $token = $res['access_token'];
        // bin2hex(random_bytes(16)) → 32 hex-символа = 128 бит энтропии.
        $this->assertSame(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        // Токен уходит в БД тем же значением, что и клиенту в ссылку успеха.
        $this->assertSame($token, $this->log->orders[0][':access_token']);
    }

    public function testAccessTokensAreNotPredictable(): void
    {
        $tokens = [];
        for ($i = 0; $i < 20; $i++) {
            $this->setUp(); // чистый лог на каждый заказ
            $res = order_create_db($this->fakeDb(), $this->checkoutData(), [cart_build_line($this->product(), 150)]);
            $tokens[] = $res['access_token'];
        }

        // Ни повторов, ни производной от номера заказа/времени.
        $this->assertCount(20, array_unique($tokens));
        foreach ($tokens as $t) {
            $this->assertStringNotContainsString(date('Ymd'), $t);
        }
    }

    public function testTokenIsAcceptedByOrderTokenValid(): void
    {
        $res = order_create_db($this->fakeDb(), $this->checkoutData(), [cart_build_line($this->product(), 150)]);
        $order = ['access_token' => $this->log->orders[0][':access_token']];

        $this->assertTrue(order_token_valid($order, $res['access_token']));
        $this->assertFalse(order_token_valid($order, str_repeat('0', 32)));
    }
}
