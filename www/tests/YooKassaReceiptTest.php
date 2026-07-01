<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/payment/YooKassaGateway.php';

class YooKassaReceiptTest extends TestCase
{
    private function order(): array
    {
        return [
            'id' => 5, 'order_number' => 'ZP-1', 'total' => '3500.00',
            'email' => 'b2b@example.ru', 'phone' => '+7 (999) 123-45-67',
            'items' => [
                ['name_snapshot' => 'Пакет ПВД 20x15', 'price_snapshot' => '150.00', 'qty' => 10],
                ['name_snapshot' => 'Пакет ПВД 30x40', 'price_snapshot' => '400.00', 'qty' => 5],
            ],
        ];
    }

    public function testBuildReceiptStructure(): void
    {
        $r = YooKassaGateway::buildReceipt($this->order(), 1);
        $this->assertNotNull($r);
        $this->assertSame('b2b@example.ru', $r['customer']['email']);
        $this->assertSame('+79991234567', $r['customer']['phone']); // нормализован
        $this->assertCount(2, $r['items']);
        $this->assertSame('Пакет ПВД 20x15', $r['items'][0]['description']);
        $this->assertSame(10, $r['items'][0]['quantity']);
        $this->assertSame('150.00', $r['items'][0]['amount']['value']);
        $this->assertSame('RUB', $r['items'][0]['amount']['currency']);
        $this->assertSame(1, $r['items'][0]['vat_code']);
        $this->assertSame('commodity', $r['items'][0]['payment_subject']);
    }

    public function testVatCodePassedThrough(): void
    {
        $r = YooKassaGateway::buildReceipt($this->order(), 4);
        $this->assertSame(4, $r['items'][0]['vat_code']);
    }

    public function testNoItemsReturnsNull(): void
    {
        $o = $this->order(); $o['items'] = [];
        $this->assertNull(YooKassaGateway::buildReceipt($o, 1));
    }

    public function testNoContactReturnsNull(): void
    {
        $o = $this->order(); $o['email'] = ''; $o['phone'] = '';
        $this->assertNull(YooKassaGateway::buildReceipt($o, 1));
    }

    public function testCreatePaymentIncludesReceipt(): void
    {
        $captured = [];
        $http = function (string $method, string $url, array $headers, ?string $body) use (&$captured): array {
            $captured = $body;
            return ['status' => 200, 'body' => json_encode(['id' => 'p1', 'confirmation' => ['confirmation_url' => 'https://u']])];
        };
        $gw = new YooKassaGateway('s', 'k', 'https://api.yookassa.ru/v3', $http, 1);
        $gw->createPayment($this->order(), 'https://ret/');
        $sent = json_decode($captured, true);
        $this->assertArrayHasKey('receipt', $sent);
        $this->assertCount(2, $sent['receipt']['items']);
        $this->assertSame('b2b@example.ru', $sent['receipt']['customer']['email']);
    }
}
