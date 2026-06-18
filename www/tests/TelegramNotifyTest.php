<?php
use PHPUnit\Framework\TestCase;

// Задаём креды ДО подключения файла, чтобы не дёргать реальный tg/config.php и не слать сообщения.
if (!defined('BOT_TOKEN')) {
    define('BOT_TOKEN', 'TEST_TOKEN');
}
if (!defined('ADMIN_CHAT_ID')) {
    define('ADMIN_CHAT_ID', '999');
}

require_once __DIR__ . '/../includes/notify/telegram_notify.php';

class TelegramNotifyTest extends TestCase
{
    public function testConfiguredWithConstants(): void
    {
        $this->assertTrue(telegram_configured());
    }

    public function testSendBuildsRequestAndReturnsTrueOn200(): void
    {
        $captured = [];
        $http = function (string $url, array $post) use (&$captured): array {
            $captured = ['url' => $url, 'post' => $post];
            return ['status' => 200, 'body' => '{"ok":true}'];
        };

        $ok = telegram_send('Привет', null, $http);

        $this->assertTrue($ok);
        $this->assertStringContainsString('/botTEST_TOKEN/sendMessage', $captured['url']);
        $this->assertSame('999', $captured['post']['chat_id']);
        $this->assertSame('Привет', $captured['post']['text']);
    }

    public function testSendReturnsFalseOnHttpError(): void
    {
        $http = fn(string $url, array $post): array => ['status' => 500, 'body' => 'err'];
        $this->assertFalse(telegram_send('x', null, $http));
    }

    public function testSendToExplicitChat(): void
    {
        $captured = [];
        $http = function (string $url, array $post) use (&$captured): array {
            $captured = $post;
            return ['status' => 200, 'body' => 'ok'];
        };
        telegram_send('hi', '555', $http);
        $this->assertSame('555', $captured['chat_id']);
    }
}
