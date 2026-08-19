<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/cart_logic.php';

/**
 * Сверка суммы платежа с суммой заказа (api/payment_callback.php) и генерация
 * номера заказа. Обе функции чистые — БД не нужна.
 */
class OrderAmountTest extends TestCase
{
    public function testEqualAmountsInDifferentNotations(): void
    {
        // amount.value от ЮKassa — «1234.00», orders.total — DECIMAL-строка
        $this->assertTrue(order_amount_equals('1234.00', '1234.00'));
        $this->assertTrue(order_amount_equals('1234.00', '1234.0000'));
        $this->assertTrue(order_amount_equals('1200.00', '1200'));
        $this->assertTrue(order_amount_equals('0.00', '0'));
        $this->assertTrue(order_amount_equals('1234.00', 1234.0));
    }

    public function testDifferenceOfOneKopeckIsDetected(): void
    {
        $this->assertFalse(order_amount_equals('1234.00', '1234.01'));
        $this->assertFalse(order_amount_equals('1233.99', '1234.00'));
        $this->assertFalse(order_amount_equals('1.00', '100.00'));
    }

    public function testFloatArtefactsDoNotBreakComparison(): void
    {
        // 0.1 + 0.2 = 0.30000000000000004 — прямое == не сработало бы
        $this->assertTrue(order_amount_equals(0.1 + 0.2, '0.30'));
        $this->assertTrue(order_amount_equals('14199.00', 14199.000000001));
    }

    public function testMissingAmountNeverMatchesRealOrder(): void
    {
        // amount.value не пришёл → считаем несовпадением, а не оплатой
        $this->assertFalse(order_amount_equals('', '1234.00'));
        $this->assertFalse(order_amount_equals(null, '1234.00'));
    }

    public function testOrderNumberSequence(): void
    {
        $ts = mktime(12, 0, 0, 6, 18, 2026);
        $this->assertSame('ZP-20260618-0001', order_number(1, $ts));
        $this->assertSame('ZP-20260618-0010', order_number(10, $ts));
        // последовательность строго возрастает и остаётся сортируемой как строка
        $this->assertLessThan(order_number(2, $ts), order_number(1, $ts));
        // за пределами 4 знаков паддинг не режет номер
        $this->assertSame('ZP-20260618-12345', order_number(12345, $ts));
        // новый день — своя нумерация с единицы
        $this->assertSame('ZP-20260619-0001', order_number(1, $ts + 86400));
    }
}
