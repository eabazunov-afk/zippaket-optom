<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/checkout_validation.php';

class CheckoutValidationTest extends TestCase
{
    private function base(array $over = []): array
    {
        return array_merge([
            'customer_name' => 'Иван', 'phone' => '+7 920 346-50-67', 'email' => 'a@b.ru',
            'customer_type' => 'individual', 'delivery_method' => 'pickup',
            'payment_method' => 'online',
        ], $over);
    }

    public function testValidIndividual(): void
    {
        $r = checkout_validate($this->base());
        $this->assertTrue($r['ok']);
        $this->assertSame([], $r['errors']);
    }

    public function testMissingNameAndBadPhone(): void
    {
        $r = checkout_validate($this->base(['customer_name' => '  ', 'phone' => '123']));
        $this->assertFalse($r['ok']);
        $this->assertArrayHasKey('customer_name', $r['errors']);
        $this->assertArrayHasKey('phone', $r['errors']);
    }

    public function testCompanyRequiresCompanyNameAndInn(): void
    {
        $r = checkout_validate($this->base(['customer_type' => 'company']));
        $this->assertFalse($r['ok']);
        $this->assertArrayHasKey('company_name', $r['errors']);
        $this->assertArrayHasKey('inn', $r['errors']);
    }

    public function testCompanyValidWithInn(): void
    {
        $r = checkout_validate($this->base([
            'customer_type' => 'company', 'company_name' => 'ООО Ромашка', 'inn' => '7707083893',
        ]));
        $this->assertTrue($r['ok']);
    }

    public function testInvoiceOnlyForCompany(): void
    {
        $r = checkout_validate($this->base(['payment_method' => 'invoice']));
        $this->assertFalse($r['ok']);
        $this->assertArrayHasKey('payment_method', $r['errors']);
    }

    public function testPhoneWithoutDigitsRejected(): void
    {
        // Старая маска пропускала «((((((((((» — телефон уходит в чек 54-ФЗ.
        foreach (['((((((((((', '+-+-+-+-+-+-', '   ---   ()', '12345'] as $phone) {
            $r = checkout_validate($this->base(['phone' => $phone]));
            $this->assertFalse($r['ok'], "принят некорректный телефон: $phone");
            $this->assertArrayHasKey('phone', $r['errors']);
        }
    }

    public function testValidPhoneFormats(): void
    {
        foreach (['+7 920 346-50-67', '89203465067', '8 (920) 346 50 67'] as $phone) {
            $r = checkout_validate($this->base(['phone' => $phone]));
            $this->assertTrue($r['ok'], "отклонён корректный телефон: $phone");
        }
    }

    public function testDeliveryAddressRequiredForCourierAndTk(): void
    {
        foreach (['courier', 'tk'] as $method) {
            $r = checkout_validate($this->base(['delivery_method' => $method]));
            $this->assertFalse($r['ok']);
            $this->assertArrayHasKey('delivery_address', $r['errors']);

            $ok = checkout_validate($this->base([
                'delivery_method' => $method, 'delivery_address' => 'Москва, ул. Ленина, 1',
            ]));
            $this->assertTrue($ok['ok']);
            $this->assertSame('Москва, ул. Ленина, 1', $ok['data']['delivery_address']);
        }
    }

    public function testPickupDoesNotRequireAddress(): void
    {
        $r = checkout_validate($this->base(['delivery_method' => 'pickup']));
        $this->assertTrue($r['ok']);
        $this->assertNull($r['data']['delivery_address']);
    }

    public function testBadDeliveryAndType(): void
    {
        $r = checkout_validate($this->base(['delivery_method' => 'plane', 'customer_type' => 'x']));
        $this->assertFalse($r['ok']);
        $this->assertArrayHasKey('delivery_method', $r['errors']);
        $this->assertArrayHasKey('customer_type', $r['errors']);
    }
}
