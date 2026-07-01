<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/home_view.php';

class HomeViewTest extends TestCase
{
    public function testPriceFormatsRubWithThousandsAndComma(): void
    {
        $this->assertSame('3 849,40', home_price(3849.40, 1.0));
        $this->assertSame('3 541,45', home_price(3849.40, 0.92)); // опт −8%
    }

    public function testSizeConvertsMmToCm(): void
    {
        $this->assertSame('25 × 30 см', home_size(250, 300));
        $this->assertSame('', home_size(null, 300));
    }

    public function testPickNewSortsByCreatedAtDesc(): void
    {
        $p = [
            ['id' => 1, 'created_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'created_at' => '2026-06-01 00:00:00'],
            ['id' => 3, 'created_at' => '2026-03-01 00:00:00'],
        ];
        $this->assertSame([2, 3], array_column(home_pick_new($p, 2), 'id'));
    }

    public function testPickHitsSortsBySoldThenStock(): void
    {
        $p = [
            ['id' => 1, 'quantity_sold' => 10, 'stock_quantity' => 500],
            ['id' => 2, 'quantity_sold' => 50, 'stock_quantity' => 10],
            ['id' => 3, 'quantity_sold' => 50, 'stock_quantity' => 900],
        ];
        $this->assertSame([3, 2, 1], array_column(home_pick_hits($p, 3), 'id'));
    }

    public function testTiersFallbackShape(): void
    {
        $tiers = home_tiers();
        $this->assertNotEmpty($tiers);
        $this->assertArrayHasKey('mult', $tiers[0]);
        $this->assertArrayHasKey('label', $tiers[0]);
        $this->assertArrayHasKey('class', $tiers[0]);
    }
}
