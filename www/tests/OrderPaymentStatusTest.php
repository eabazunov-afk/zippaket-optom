<?php
use PHPUnit\Framework\TestCase;

// order.php тянет config.php (только определения функций — коннект создаётся
// лениво в getDbConnection и здесь не вызывается): вся работа с БД идёт через
// order_apply_payment_status_db с фейковым PDO.
require_once __DIR__ . '/../includes/order.php';

class OrderPaymentStatusTest extends TestCase
{
    /** Строка orders для фейка: минимальный набор полей, которые читает код. */
    private function orderRow(string $status = 'pending_payment'): array
    {
        return [
            'id' => 7,
            'payment_id' => 'pay_1',
            'status' => $status,
            'payment_status' => 'pending',
        ];
    }

    /**
     * Мини-фейк БД: одна строка orders в памяти, понимает три запроса из order.php
     * (SELECT по payment_id, атомарный UPDATE статуса, UPDATE payment_status).
     * $row меняется по ссылке — после вызова можно проверить итоговое состояние.
     * $afterSelect имитирует параллельный webhook: срабатывает сразу после SELECT.
     */
    private function fakeDb(?array &$row, ?callable $afterSelect = null): PDO
    {
        $state = (object)['sql' => '', 'fetch' => false, 'rowCount' => 0];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(
            function (?array $params = null) use ($state, &$row, $afterSelect): bool {
                $params = $params ?? [];
                if (str_starts_with($state->sql, 'SELECT')) {
                    $state->fetch = ($row !== null && $row['payment_id'] === $params[0])
                        ? ['id' => $row['id'], 'status' => $row['status']]
                        : false;
                    if ($afterSelect !== null) {
                        $afterSelect($row); // «параллельный» webhook успел изменить строку
                    }
                    return true;
                }
                if (str_contains($state->sql, 'SET status =')) {
                    // UPDATE orders SET status=?, payment_status=? WHERE id=? AND status=?
                    [$target, $providerStatus, $id, $expected] = $params;
                    $match = $row !== null && (int)$row['id'] === (int)$id && $row['status'] === $expected;
                    if ($match) {
                        $row['status'] = $target;
                        $row['payment_status'] = $providerStatus;
                    }
                    $state->rowCount = $match ? 1 : 0;
                    return true;
                }
                // UPDATE orders SET payment_status = ? WHERE id = ?
                if ($row !== null && (int)$row['id'] === (int)$params[1]) {
                    $row['payment_status'] = $params[0];
                    $state->rowCount = 1;
                } else {
                    $state->rowCount = 0;
                }
                return true;
            }
        );
        $stmt->method('fetch')->willReturnCallback(fn() => $state->fetch);
        $stmt->method('rowCount')->willReturnCallback(fn() => $state->rowCount);

        $db = $this->createMock(PDO::class);
        $db->method('prepare')->willReturnCallback(function (string $sql) use ($state, $stmt) {
            $state->sql = ltrim($sql);
            return $stmt;
        });
        return $db;
    }

    public function testFirstWebhookAppliesPaid(): void
    {
        $row = $this->orderRow();
        $db = $this->fakeDb($row);

        $this->assertSame('applied', order_apply_payment_status_db($db, 'pay_1', 'succeeded', true));
        $this->assertSame('paid', $row['status']);
        $this->assertSame('succeeded', $row['payment_status']);
    }

    public function testRepeatedWebhookIsNoop(): void
    {
        $row = $this->orderRow();
        $db = $this->fakeDb($row);

        // ЮKassa ретраит уведомление — второй раз уведомлять клиента нельзя.
        $this->assertSame('applied', order_apply_payment_status_db($db, 'pay_1', 'succeeded', true));
        $this->assertSame('noop', order_apply_payment_status_db($db, 'pay_1', 'succeeded', true));
        $this->assertSame('noop', order_apply_payment_status_db($db, 'pay_1', 'succeeded', true));
        $this->assertSame('paid', $row['status']);
    }

    public function testParallelWebhookLosingRaceGetsNoop(): void
    {
        $row = $this->orderRow();
        // Гонка: между SELECT и UPDATE другой webhook уже перевёл заказ в paid.
        $db = $this->fakeDb($row, function (?array &$r): void {
            $r['status'] = 'paid';
            $r['payment_status'] = 'succeeded';
        });

        $this->assertSame('noop', order_apply_payment_status_db($db, 'pay_1', 'succeeded', true));
        $this->assertSame('paid', $row['status']);
    }

    public function testCanceledIsApplied(): void
    {
        $row = $this->orderRow();
        $db = $this->fakeDb($row);

        $this->assertSame('applied', order_apply_payment_status_db($db, 'pay_1', 'canceled', false));
        $this->assertSame('canceled', $row['status']);
        $this->assertSame('canceled', $row['payment_status']);
    }

    public function testForbiddenTransitionDoesNotChangeStatus(): void
    {
        // shipped → canceled запрещён матрицей order_status.php
        $row = $this->orderRow('shipped');
        $db = $this->fakeDb($row);

        $this->assertSame('forbidden', order_apply_payment_status_db($db, 'pay_1', 'canceled', false));
        $this->assertSame('shipped', $row['status']);
        $this->assertSame('canceled', $row['payment_status']); // аудит пишем всегда
    }

    public function testIntermediateStatusIsIgnored(): void
    {
        $row = $this->orderRow();
        $db = $this->fakeDb($row);

        $this->assertSame('ignored', order_apply_payment_status_db($db, 'pay_1', 'waiting_for_capture', false));
        $this->assertSame('pending_payment', $row['status']);
        $this->assertSame('waiting_for_capture', $row['payment_status']);
    }

    public function testSucceededWithoutPaidIsIgnored(): void
    {
        $row = $this->orderRow();
        $db = $this->fakeDb($row);

        $this->assertSame('ignored', order_apply_payment_status_db($db, 'pay_1', 'succeeded', false));
        $this->assertSame('pending_payment', $row['status']);
    }

    public function testUnknownPaymentIdIsNotFound(): void
    {
        $row = $this->orderRow();
        $db = $this->fakeDb($row);

        $this->assertSame('not_found', order_apply_payment_status_db($db, 'pay_unknown', 'succeeded', true));
        $this->assertSame('pending_payment', $row['status']);
        $this->assertSame('pending', $row['payment_status']);
    }

    public function testMissingOrderIsNotFound(): void
    {
        $row = null;
        $db = $this->fakeDb($row);

        $this->assertSame('not_found', order_apply_payment_status_db($db, 'pay_1', 'succeeded', true));
    }
}
