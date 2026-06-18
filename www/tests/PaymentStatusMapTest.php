<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/payment/payment_status_map.php';

class PaymentStatusMapTest extends TestCase
{
    public function testSucceededPaidBecomesPaid(): void
    {
        $this->assertSame('paid', yookassa_target_order_status('succeeded', true));
    }

    public function testSucceededButNotPaidIsIgnored(): void
    {
        // редкий случай: статус succeeded, но paid=false — не считаем оплаченным
        $this->assertNull(yookassa_target_order_status('succeeded', false));
    }

    public function testCanceledBecomesCanceled(): void
    {
        $this->assertSame('canceled', yookassa_target_order_status('canceled', false));
    }

    public function testPendingAndWaitingAreIgnored(): void
    {
        $this->assertNull(yookassa_target_order_status('pending', false));
        $this->assertNull(yookassa_target_order_status('waiting_for_capture', false));
    }
}
