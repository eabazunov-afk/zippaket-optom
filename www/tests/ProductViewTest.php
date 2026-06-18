<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/cart_quantity.php';
require_once __DIR__ . '/../includes/product_view.php';

class ProductViewTest extends TestCase
{
    public function testFormatPrice(): void
    {
        $this->assertSame('94,66 ₽', pv_format_price(94.66));
        $this->assertSame('1 250,00 ₽', pv_format_price(1250));
    }

    public function testFormatSize(): void
    {
        $this->assertSame('200×150 мм', pv_format_size(200, 150));
        $this->assertSame('', pv_format_size(null, null));
    }

    public function testStockStatusInStock(): void
    {
        $s = pv_stock_status(12100);
        $this->assertTrue($s['in_stock']);
        $this->assertSame('В наличии', $s['label']);
        $this->assertSame('12 100 шт', $s['count_label']);
    }

    public function testStockStatusOutOfStock(): void
    {
        $s = pv_stock_status(0);
        $this->assertFalse($s['in_stock']);
        $this->assertSame('Под заказ', $s['label']);
        $this->assertSame('', $s['count_label']);
    }

    public function testDefaultQty(): void
    {
        $this->assertSame(100, pv_default_qty(100, 50));
        $this->assertSame(1, pv_default_qty(1, 1));
    }

    public function testPackNote(): void
    {
        $this->assertSame('мин. 100 · кратно 50', pv_pack_note(100, 50));
        $this->assertSame('мин. 100', pv_pack_note(100, 1));
    }
}
