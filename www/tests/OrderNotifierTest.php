<?php
use PHPUnit\Framework\TestCase;

// Подменяем окружение до подключения модуля: order_notifier.php тянет config.php
// (сессия, БД, константы) только если sendEmail() ещё не определена.
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message, $from = '') { return false; }
}
require_once __DIR__ . '/../includes/notify/order_notifier.php';

class OrderNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        notify_deadline(null, true); // сбрасываем бюджет между тестами
    }

    protected function tearDown(): void
    {
        notify_deadline(null, true);
    }

    public function testChannelRunsWithoutBudget(): void
    {
        $ran = false;
        $ok = notify_channel('test', function () use (&$ran) { $ran = true; return true; });
        $this->assertTrue($ok);
        $this->assertTrue($ran);
    }

    public function testChannelRunsWhileBudgetLeft(): void
    {
        notify_start_budget(5.0);
        $this->assertGreaterThan(0, notify_time_left());
        $this->assertTrue(notify_channel('test', fn() => true));
    }

    public function testChannelSkippedWhenBudgetExhausted(): void
    {
        notify_start_budget(-1.0); // дедлайн уже в прошлом
        $ran = false;
        $ok = notify_channel('test', function () use (&$ran) { $ran = true; return true; });
        $this->assertFalse($ok);
        $this->assertFalse($ran, 'канал не должен стартовать после исчерпания бюджета');
    }

    public function testChannelSwallowsExceptions(): void
    {
        $this->assertFalse(notify_channel('test', function () { throw new RuntimeException('boom'); }));
    }

    public function testTimeLeftNullWithoutBudget(): void
    {
        $this->assertNull(notify_time_left());
    }
}
