<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/cart_quantity.php';

class CartQuantityTest extends TestCase
{
    public function testBelowMinimumRaisesToMinimum(): void
    {
        $this->assertSame(100, normalize_quantity(30, 100, 50));
    }

    public function testRoundsUpToStepFromMinimum(): void
    {
        // min=100, step=50: допустимы 100,150,200... 120 -> 150
        $this->assertSame(150, normalize_quantity(120, 100, 50));
    }

    public function testExactValidValueUnchanged(): void
    {
        $this->assertSame(200, normalize_quantity(200, 100, 50));
    }

    public function testStepZeroTreatedAsOne(): void
    {
        $this->assertSame(137, normalize_quantity(137, 1, 0));
    }

    public function testIsValidQuantity(): void
    {
        $this->assertTrue(is_valid_quantity(150, 100, 50));
        $this->assertFalse(is_valid_quantity(120, 100, 50));
        $this->assertFalse(is_valid_quantity(50, 100, 50));
    }
}
