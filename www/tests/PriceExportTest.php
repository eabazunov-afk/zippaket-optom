<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/home_view.php';
require_once __DIR__ . '/../includes/price_export.php';

class PriceExportTest extends TestCase
{
    public function testHeaderAndRowShape(): void
    {
        $tiers = [['label' => 'Опт', 'mult' => 0.9, 'class' => '']];
        $rows = price_rows([
            ['full_name' => 'Zip 25x30', 'width' => 250, 'height' => 300, 'thickness' => 40,
             'min_order_qty' => 100, 'stock_quantity' => 5000, 'price_rub' => 2.0],
        ], $tiers);
        $this->assertSame(['Наименование', 'Размер', 'Толщина, мкм', 'Мин. партия', 'Наличие', 'Опт'], $rows[0]);
        $this->assertSame('Zip 25x30', $rows[1][0]);
        $this->assertSame('25 × 30 см', $rows[1][1]);
        $this->assertSame('1,80', $rows[1][5]); // 2.0 * 0.9
    }

    public function testSkipsProductsWithoutPrice(): void
    {
        $rows = price_rows([['full_name' => 'X', 'price_rub' => 0]], [['label' => 'A', 'mult' => 1.0, 'class' => '']]);
        $this->assertCount(1, $rows); // только заголовок
    }
}
