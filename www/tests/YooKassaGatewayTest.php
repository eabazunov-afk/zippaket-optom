<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/payment/YooKassaGateway.php';

class YooKassaGatewayTest extends TestCase
{
    /** Фейковый HTTP-клиент: запоминает вызов, отдаёт заранее заданный ответ. */
    private function fakeHttp(array $response, array &$captured): callable
    {
        return function (string $method, string $url, array $headers, ?string $body) use ($response, &$captured): array {
            $captured = compact('method', 'url', 'headers', 'body');
            return $response;
        };
    }

    public function testCreatePaymentBuildsRequestAndParsesResponse(): void
    {
        $captured = [];
        $http = $this->fakeHttp([
            'status' => 200,
            'body' => json_encode([
                'id' => 'pay_123',
                'status' => 'pending',
                'confirmation' => ['confirmation_url' => 'https://yoomoney.ru/checkout/pay_123'],
            ]),
        ], $captured);

        $gw = new YooKassaGateway('shop_1', 'secret_1', 'https://api.yookassa.ru/v3', $http);
        $res = $gw->createPayment(
            ['id' => 42, 'order_number' => 'ZP-20260618-001', 'total' => '1500.00'],
            'https://example.test/order_success.php?order=ZP-20260618-001'
        );

        $this->assertSame('pay_123', $res['payment_id']);
        $this->assertSame('https://yoomoney.ru/checkout/pay_123', $res['confirmation_url']);

        // запрос построен верно
        $this->assertSame('POST', $captured['method']);
        $this->assertSame('https://api.yookassa.ru/v3/payments', $captured['url']);
        $this->assertContains('Authorization: Basic ' . base64_encode('shop_1:secret_1'), $captured['headers']);
        $this->assertContains('Idempotence-Key: order-ZP-20260618-001', $captured['headers']);

        $body = json_decode($captured['body'], true);
        $this->assertSame('1500.00', $body['amount']['value']);
        $this->assertSame('RUB', $body['amount']['currency']);
        $this->assertTrue($body['capture']);
        $this->assertSame('redirect', $body['confirmation']['type']);
        $this->assertSame('https://example.test/order_success.php?order=ZP-20260618-001', $body['confirmation']['return_url']);
        $this->assertSame('42', $body['metadata']['order_id']);
        $this->assertSame('ZP-20260618-001', $body['metadata']['order_number']);
    }

    public function testCreatePaymentThrowsOnHttpError(): void
    {
        $captured = [];
        $http = $this->fakeHttp(['status' => 401, 'body' => '{"type":"error"}'], $captured);
        $gw = new YooKassaGateway('shop', 'bad', 'https://api.yookassa.ru/v3', $http);

        $this->expectException(RuntimeException::class);
        $gw->createPayment(['id' => 1, 'order_number' => 'X', 'total' => '10.00'], 'https://t/');
    }

    public function testParseCallbackExtractsObject(): void
    {
        $gw = new YooKassaGateway('s', 'k');
        $raw = json_encode([
            'event' => 'payment.succeeded',
            'object' => ['id' => 'pay_9', 'status' => 'succeeded', 'paid' => true],
        ]);
        $out = $gw->parseCallback([], $raw);
        $this->assertSame('pay_9', $out['payment_id']);
        $this->assertSame('succeeded', $out['status']);
        $this->assertTrue($out['paid']);
    }

    public function testVerifySignatureByIp(): void
    {
        $gw = new YooKassaGateway('s', 'k');
        // адрес из диапазона 185.71.76.0/27
        $this->assertTrue($gw->verifySignature(['REMOTE_ADDR' => '185.71.76.5'], ''));
        // случайный внешний адрес — отклоняем
        $this->assertFalse($gw->verifySignature(['REMOTE_ADDR' => '8.8.8.8'], ''));
    }

    public function testIpInCidr(): void
    {
        $this->assertTrue(YooKassaGateway::ipInCidr('77.75.153.10', '77.75.153.0/25'));
        $this->assertFalse(YooKassaGateway::ipInCidr('77.75.153.200', '77.75.153.0/25'));
        $this->assertTrue(YooKassaGateway::ipInCidr('77.75.156.11', '77.75.156.11/32'));
        // несовпадение семейств адресов не должно падать
        $this->assertFalse(YooKassaGateway::ipInCidr('::1', '77.75.153.0/25'));
    }
}
