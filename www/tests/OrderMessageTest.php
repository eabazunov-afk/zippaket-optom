<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/notify/order_message.php';

class OrderMessageTest extends TestCase
{
    private function order(array $over = []): array
    {
        return array_merge([
            'order_number' => 'ZP-20260618-001',
            'status' => 'pending_payment',
            'customer_type' => 'company',
            'customer_name' => 'ООО Ромашка',
            'phone' => '+79991234567',
            'email' => 'b2b@example.ru',
            'company_name' => 'ООО Ромашка',
            'inn' => '7701234567',
            'delivery_method' => 'tk',
            'delivery_address' => 'Москва, Тверская 1',
            'payment_method' => 'online',
            'comment' => 'позвонить заранее',
            'total' => '3500.00',
        ], $over);
    }

    private function items(): array
    {
        return [
            ['name_snapshot' => 'Пакет ПВД 20x15', 'qty' => 10, 'line_total' => '1500.00'],
            ['name_snapshot' => 'Пакет ПВД 30x40', 'qty' => 5,  'line_total' => '2000.00'],
        ];
    }

    public function testMoneyFormat(): void
    {
        $this->assertSame('1 500,00 ₽', notify_money(1500));
        $this->assertSame('0,00 ₽', notify_money(0));
    }

    public function testTelegramContainsKeyFields(): void
    {
        $txt = format_order_telegram($this->order(), $this->items(), 'new');
        $this->assertStringContainsString('🛒 Новый заказ ZP-20260618-001', $txt);
        $this->assertStringContainsString('Юрлицо', $txt);
        $this->assertStringContainsString('ИНН 7701234567', $txt);
        $this->assertStringContainsString('Транспортная компания', $txt);
        $this->assertStringContainsString('Картой онлайн', $txt);
        $this->assertStringContainsString('Пакет ПВД 20x15 × 10 = 1 500,00 ₽', $txt);
        $this->assertStringContainsString('Итого: 3 500,00 ₽', $txt);
    }

    public function testTelegramPaidHeader(): void
    {
        $txt = format_order_telegram($this->order(['status' => 'paid']), $this->items(), 'paid');
        $this->assertStringContainsString('💰 Заказ оплачен', $txt);
        $this->assertStringContainsString('Оплачен', $txt);
    }

    public function testEmailSubjectAndBody(): void
    {
        $mail = format_order_email($this->order(), $this->items(), 'new');
        $this->assertStringContainsString('Новый заказ ZP-20260618-001', $mail['subject']);
        $this->assertStringContainsString('3 500,00 ₽', $mail['subject']);
        $this->assertStringContainsString('<table', $mail['body']);
        $this->assertStringContainsString('Пакет ПВД 30x40', $mail['body']);
        $this->assertStringContainsString('Итого', $mail['body']);
    }

    public function testEmailEscapesHtml(): void
    {
        $order = $this->order(['customer_name' => 'A<script>x</script>']);
        $mail = format_order_email($order, $this->items(), 'new');
        $this->assertStringNotContainsString('<script>', $mail['body']);
        $this->assertStringContainsString('&lt;script&gt;', $mail['body']);
    }

    public function testAmocrmMapping(): void
    {
        $data = order_to_amocrm_data($this->order(), $this->items());
        $this->assertSame('ООО Ромашка', $data['name']);
        $this->assertSame('order', $data['type']);
        $this->assertSame('website-order', $data['source']);
        $this->assertStringContainsString('Заказ ZP-20260618-001', $data['message']);
        $this->assertStringContainsString('Пакет ПВД 20x15', $data['message']);
        $this->assertSame('ZP-20260618-001', $data['parameters']['order_number']);
    }

    public function testIndividualWithoutCompanyFields(): void
    {
        $order = $this->order(['customer_type' => 'individual', 'company_name' => null, 'inn' => null]);
        $txt = format_order_telegram($order, $this->items(), 'new');
        $this->assertStringContainsString('Физлицо', $txt);
        $this->assertStringNotContainsString('Организация:', $txt);
    }
}
