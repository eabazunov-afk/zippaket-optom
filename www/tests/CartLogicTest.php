<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/cart_quantity.php';
require_once __DIR__ . '/../includes/cart_logic.php';

class CartLogicTest extends TestCase
{
    private function product(array $over = []): array
    {
        return array_merge([
            'id' => 1, 'full_name' => 'Пакет 20x15', 'price_rub' => '94.66',
            'min_order_qty' => 100, 'qty_step' => 50,
        ], $over);
    }

    public function testBuildLineNormalizesQtyAndComputesTotal(): void
    {
        $line = cart_build_line($this->product(), 120);
        $this->assertSame(1, $line['product_id']);
        $this->assertSame('Пакет 20x15', $line['name']);
        $this->assertSame(94.66, $line['price']);
        $this->assertSame(150, $line['qty']);
        $this->assertSame(14199.0, $line['line_total']);
    }

    public function testBuildLineBelowMinRaises(): void
    {
        $line = cart_build_line($this->product(), 10);
        $this->assertSame(100, $line['qty']);
    }

    public function testBuildLineNullPrice(): void
    {
        $line = cart_build_line($this->product(['price_rub' => null]), 100);
        $this->assertSame(0.0, $line['price']);
        $this->assertSame(0.0, $line['line_total']);
    }

    public function testTotals(): void
    {
        $lines = [
            ['product_id'=>1,'name'=>'A','price'=>94.66,'qty'=>150,'line_total'=>14199.0],
            ['product_id'=>2,'name'=>'B','price'=>10.0,'qty'=>100,'line_total'=>1000.0],
        ];
        $t = cart_totals($lines);
        $this->assertSame(15199.0, $t['items_total']);
        $this->assertSame(250, $t['total_qty']);
        $this->assertSame(2, $t['positions']);
    }

    public function testOrderNumberFormat(): void
    {
        $ts = mktime(12, 0, 0, 6, 18, 2026);
        $this->assertSame('ZP-20260618-0007', order_number(7, $ts));
        $this->assertSame('ZP-20260618-1234', order_number(1234, $ts));
    }
}
