<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/cart_quantity.php';
require_once __DIR__ . '/../includes/home_view.php';
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

    // --- Оптовые ступени ---------------------------------------------------

    public function testTierMinQtyFromLabel(): void
    {
        $this->assertSame(300000, wholesale_tier_min_qty(['label' => 'Опт от 300к']));
        $this->assertSame(20000, wholesale_tier_min_qty(['label' => 'Опт от 20к']));
        $this->assertSame(3000, wholesale_tier_min_qty(['label' => 'Розница от 3к']));
        $this->assertSame(20000, wholesale_tier_min_qty(['label' => 'Опт от 20 000']));
        $this->assertSame(0, wholesale_tier_min_qty(['label' => 'Розница']));
    }

    public function testTierMinQtyExplicitKeyWins(): void
    {
        $this->assertSame(500, wholesale_tier_min_qty(['label' => 'Опт от 300к', 'min_qty' => 500]));
    }

    public function testTierForQtyBoundaries(): void
    {
        $this->assertSame(1.0, wholesale_multiplier(0));
        $this->assertSame(1.0, wholesale_multiplier(2999));
        $this->assertSame(1.0, wholesale_multiplier(3000));
        $this->assertSame(1.0, wholesale_multiplier(19999));
        $this->assertSame(0.92, wholesale_multiplier(20000));
        $this->assertSame(0.92, wholesale_multiplier(299999));
        $this->assertSame(0.82, wholesale_multiplier(300000));
        $this->assertSame(0.82, wholesale_multiplier(10000000));
    }

    public function testTiersInjectableAndSortedByThreshold(): void
    {
        // Порядок в конфиге не важен — функция сама сортирует по убыванию порога.
        $tiers = [
            ['label' => 'от 100', 'mult' => 0.9],
            ['label' => 'от 1000', 'mult' => 0.5],
        ];
        $this->assertSame(0.9, wholesale_multiplier(100, $tiers));
        $this->assertSame(0.5, wholesale_multiplier(1000, $tiers));
        $this->assertSame(1.0, wholesale_multiplier(99, $tiers));
    }

    public function testUnitPriceMatchesShowcaseFormatting(): void
    {
        // Витрина/XLS печатают home_price(base, mult); корзина считает
        // wholesale_unit_price(). Оба берут ступени из одного источника —
        // числа обязаны совпадать до копейки.
        $base = 3849.40;
        $this->assertSame(home_price($base, 0.82), number_format(wholesale_unit_price($base, 300000), 2, ',', ' '));
        $this->assertSame(home_price($base, 0.92), number_format(wholesale_unit_price($base, 20000), 2, ',', ' '));
        $this->assertSame(home_price($base, 1.0), number_format(wholesale_unit_price($base, 100), 2, ',', ' '));
    }

    public function testBuildLineAppliesWholesaleTier(): void
    {
        $line = cart_build_line($this->product(), 300000);
        $this->assertSame(94.66, $line['base_price']);
        $this->assertSame(77.62, $line['price']);          // 94.66 × 0.82
        $this->assertSame(0.82, $line['tier_mult']);
        $this->assertSame('Опт от 300к', $line['tier_label']);
        $this->assertSame(300000, $line['qty']);
        $this->assertSame(23286000.0, $line['line_total']); // снапшот заказа = уже со скидкой
    }

    public function testBuildLineMiddleTier(): void
    {
        $line = cart_build_line($this->product(), 20000);
        $this->assertSame(87.09, $line['price']);           // 94.66 × 0.92
        $this->assertSame(1741800.0, $line['line_total']);
    }

    public function testTierIsChosenAfterQuantityNormalization(): void
    {
        // 2999 нормализуется до 3000 (min 100, шаг 50) — ступень считается от 3000.
        $line = cart_build_line($this->product(), 2999);
        $this->assertSame(3000, $line['qty']);
        $this->assertSame(1.0, $line['tier_mult']);
        $this->assertSame(94.66, $line['price']);
    }

    // --- Цена по запросу / доступность ------------------------------------

    public function testNullPriceLineIsUnavailable(): void
    {
        $line = cart_build_line($this->product(['price_rub' => null]), 100);
        $this->assertFalse($line['available']);
        $this->assertTrue($line['price_missing']);
        $this->assertSame(0.0, $line['line_total']);
    }

    public function testZeroPriceLineIsUnavailable(): void
    {
        $line = cart_build_line($this->product(['price_rub' => '0.00']), 100);
        $this->assertFalse($line['available']);
    }

    public function testCheckoutIssuesBlockZeroTotal(): void
    {
        $lines = [cart_build_line($this->product(['price_rub' => null]), 100)];
        $issues = cart_checkout_issues($lines);
        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('цена по запросу', mb_strtolower($issues[0]));
    }

    public function testCheckoutIssuesBlockGoneProduct(): void
    {
        $lines = [
            cart_build_line($this->product(), 100),
            cart_unavailable_line(77, 100, 'Пакет 30x40'),
        ];
        $issues = cart_checkout_issues($lines);
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('Пакет 30x40', $issues[0]);
    }

    public function testCheckoutIssuesEmptyForEmptyCart(): void
    {
        // Пустая корзина — не «нулевая сумма»: этот случай обрабатывается отдельно.
        $this->assertSame([], cart_checkout_issues([]));
    }

    public function testCheckoutIssuesEmptyForValidCart(): void
    {
        $this->assertSame([], cart_checkout_issues([cart_build_line($this->product(), 100)]));
    }

    public function testCheckoutIssuesOnZeroSumWithoutMissingPrice(): void
    {
        // Искусственный случай: строки есть, сумма 0 — оформление запрещено.
        $lines = [['name' => 'X', 'available' => true, 'qty' => 1, 'line_total' => 0.0]];
        $this->assertNotEmpty(cart_checkout_issues($lines));
    }

    // --- Наличие -----------------------------------------------------------

    public function testBackorderQtyComputed(): void
    {
        $line = cart_build_line($this->product(['stock_quantity' => 1000]), 5000);
        $this->assertSame(4000, $line['backorder_qty']);
        $this->assertTrue($line['available']); // остаток не блокирует: делаем под заказ
        $this->assertCount(1, cart_stock_warnings([$line]));
    }

    public function testNoBackorderWhenStockEnough(): void
    {
        $line = cart_build_line($this->product(['stock_quantity' => 100000]), 5000);
        $this->assertSame(0, $line['backorder_qty']);
        $this->assertSame([], cart_stock_warnings([$line]));
    }

    public function testUnknownStockDoesNotWarn(): void
    {
        $line = cart_build_line($this->product(), 5000); // stock_quantity отсутствует
        $this->assertNull($line['stock_qty']);
        $this->assertSame(0, $line['backorder_qty']);
    }

    // --- Границы количества ------------------------------------------------

    public function testClampQty(): void
    {
        $this->assertSame(0, cart_clamp_qty(-5));
        $this->assertSame(150, cart_clamp_qty(150));
        $this->assertSame(CART_MAX_QTY, cart_clamp_qty(PHP_INT_MAX));
    }

    public function testHugeQtyDoesNotOverflowDecimal(): void
    {
        // DECIMAL(15,2) вмещает < 1e13. Раньше qty=PHP_INT_MAX давал непредставимую сумму.
        $line = cart_build_line($this->product(), (int)'9999999999999999999');
        $this->assertLessThanOrEqual(CART_MAX_QTY, $line['qty']);
        $this->assertLessThan(1.0e13, $line['line_total']);
    }

    public function testParseQtyInputDistinguishesEmptyFromZero(): void
    {
        $empty = cart_parse_qty_input('');
        $this->assertFalse($empty['ok']);
        $this->assertSame('empty', $empty['error']);

        $missing = cart_parse_qty_input(null);
        $this->assertFalse($missing['ok']);
        $this->assertSame('missing', $missing['error']);

        $zero = cart_parse_qty_input('0');
        $this->assertTrue($zero['ok']);
        $this->assertSame(0, $zero['qty']);
    }

    public function testParseQtyInputRejectsNonNumbersAndClampsHuge(): void
    {
        $this->assertFalse(cart_parse_qty_input('abc')['ok']);
        $this->assertFalse(cart_parse_qty_input('-5')['ok']);
        $this->assertFalse(cart_parse_qty_input('1.5')['ok']);

        $huge = cart_parse_qty_input('9999999999999999999');
        $this->assertTrue($huge['ok']);
        $this->assertSame(CART_MAX_QTY, $huge['qty']);
        $this->assertTrue($huge['clamped']);
    }

    public function testTotalsIgnoreUnavailableLines(): void
    {
        $lines = [
            cart_build_line($this->product(), 100),
            cart_unavailable_line(9, 100, 'Снятый товар'),
        ];
        $t = cart_totals($lines);
        $this->assertSame(9466.0, $t['items_total']);
        $this->assertSame(2, $t['positions']);
    }
}
