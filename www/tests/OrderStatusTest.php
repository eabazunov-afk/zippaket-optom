<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/order_status.php';

class OrderStatusTest extends TestCase
{
    public function testValidStatusesList(): void
    {
        $this->assertContains('pending_payment', order_statuses());
        $this->assertContains('paid', order_statuses());
        $this->assertCount(7, order_statuses());
    }

    public function testAllowedTransitions(): void
    {
        $this->assertTrue(can_transition('new', 'pending_payment'));
        $this->assertTrue(can_transition('pending_payment', 'paid'));
        $this->assertTrue(can_transition('paid', 'processing'));
        $this->assertTrue(can_transition('shipped', 'done'));
    }

    public function testCancelAllowedBeforeShipping(): void
    {
        $this->assertTrue(can_transition('new', 'canceled'));
        $this->assertTrue(can_transition('paid', 'canceled'));
    }

    public function testForbiddenTransitions(): void
    {
        $this->assertFalse(can_transition('new', 'paid'));        // нельзя перепрыгнуть оплату
        $this->assertFalse(can_transition('done', 'processing')); // терминальный статус
        $this->assertFalse(can_transition('canceled', 'paid'));   // терминальный статус
        $this->assertFalse(can_transition('shipped', 'canceled'));// отгружено — не отменить
    }
}
