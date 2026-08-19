<?php
use PHPUnit\Framework\TestCase;

// Тестируем чистую логику цен материалов без обращения к БД: конструктор
// Calculator и app_settings подключают config.php (только определения функций,
// коннект создаётся лениво в getDbConnection и здесь не вызывается).
require_once __DIR__ . '/../includes/app_settings.php';
require_once __DIR__ . '/../includes/calculator.php';

class CalculatorSettingsTest extends TestCase
{
    public function testDefaultsWhenNoOverrides(): void
    {
        $this->assertSame(
            ['EVA' => 380.0, 'ПВД' => 360.0],
            calc_material_prices([])
        );
    }

    public function testOverrideMergesOverDefaults(): void
    {
        $prices = calc_material_prices(['EVA' => 500]);
        $this->assertSame(500.0, $prices['EVA']);
        $this->assertSame(360.0, $prices['ПВД']); // не переопределён — дефолт
    }

    public function testNumericStringIsAccepted(): void
    {
        $prices = calc_material_prices(['ПВД' => '410.5']);
        $this->assertSame(410.5, $prices['ПВД']);
    }

    public function testNonPositiveAndNonNumericFallBackToDefault(): void
    {
        $prices = calc_material_prices(['EVA' => 0, 'ПВД' => -5]);
        $this->assertSame(380.0, $prices['EVA']);
        $this->assertSame(360.0, $prices['ПВД']);

        $prices2 = calc_material_prices(['EVA' => 'abc', 'ПВД' => null]);
        $this->assertSame(380.0, $prices2['EVA']);
        $this->assertSame(360.0, $prices2['ПВД']);
    }

    public function testCalculatorUsesInjectedPrices(): void
    {
        // 25×30 см, 60 мкм, слайдер, EVA=500 ₽/кг, 1000 шт (без скидки).
        // Вес: площадь 0.15 м² × 60 × 0.92 = 8.28 г плёнка + 1.6 молния
        //      + 0.25 бегунок = 10.13 г = 0.01013 кг.
        // Базовая цена = 0.01013 × 500 = 5.065 → округл. 5.07 ₽/шт.
        $calc = new Calculator(['EVA' => 500, 'ПВД' => 360]);
        $result = $calc->calculatePrice('slider', 25, 30, 60, 'EVA', 1000);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['discount_percent']);
        $this->assertEqualsWithDelta(5.07, $result['base_price'], 0.01);
        $this->assertEqualsWithDelta(5.07, $result['unit_price'], 0.01);
    }

    public function testCalculatorDefaultMaterialIsEva380WhenInjectedDefault(): void
    {
        // Тот же пакет на дефолтном EVA=380: базовая цена 0.01013×380=3.8494 → 3.85.
        $calc = new Calculator(['EVA' => 380, 'ПВД' => 360]);
        $result = $calc->calculatePrice('slider', 25, 30, 60, 'EVA', 1000);
        $this->assertEqualsWithDelta(3.85, $result['unit_price'], 0.01);
    }
}
