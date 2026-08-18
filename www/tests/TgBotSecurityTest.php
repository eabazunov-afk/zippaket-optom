<?php
use PHPUnit\Framework\TestCase;

// tg/bot_lib.php не имеет побочных эффектов при подключении — можно тестировать.
require_once __DIR__ . '/../tg/bot_lib.php';

class TgBotSecurityTest extends TestCase
{
    // --- Секрет вебхука -----------------------------------------------------

    public function testSecretValidOnExactMatch(): void
    {
        $this->assertTrue(tg_secret_valid('s3cr3t', 's3cr3t'));
    }

    public function testSecretInvalidOnMismatch(): void
    {
        $this->assertFalse(tg_secret_valid('s3cr3T', 's3cr3t'));
        $this->assertFalse(tg_secret_valid('s3cr3t ', 's3cr3t'));
    }

    /** Без заголовка запрос не проходит. */
    public function testSecretInvalidWhenHeaderMissing(): void
    {
        $this->assertFalse(tg_secret_valid(null, 's3cr3t'));
        $this->assertFalse(tg_secret_valid('', 's3cr3t'));
    }

    /** Fail-closed: секрет не настроен на сервере — принимать нельзя. */
    public function testSecretInvalidWhenNotConfigured(): void
    {
        $this->assertFalse(tg_secret_valid('что угодно', null));
        $this->assertFalse(tg_secret_valid('что угодно', ''));
    }

    // --- Нормализация user_id ----------------------------------------------

    public function testUserIdAcceptsPositiveIntegers(): void
    {
        $this->assertSame(123456, tg_user_id(123456));
        $this->assertSame(123456, tg_user_id('123456'));
    }

    public function testUserIdRejectsTraversalAndGarbage(): void
    {
        $this->assertSame(0, tg_user_id('../../../uploads/x'));
        $this->assertSame(0, tg_user_id('123/../../x'));
        $this->assertSame(0, tg_user_id('12abc'));
        $this->assertSame(0, tg_user_id(''));
        $this->assertSame(0, tg_user_id(null));
        $this->assertSame(0, tg_user_id(-5));
        $this->assertSame(0, tg_user_id(0));
        $this->assertSame(0, tg_user_id(['id' => 1]));
    }

    // --- Пути к файлам состояний -------------------------------------------

    public function testUserFileStaysInsideDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/tg_users_test';
        $file = tg_user_file(777, 'state', $dir);

        $this->assertSame($dir . DIRECTORY_SEPARATOR . '777_state.txt', $file);
        $this->assertStringNotContainsString('..', $file);
    }

    public function testUserFileEmptyForBadIdOrKind(): void
    {
        $dir = sys_get_temp_dir() . '/tg_users_test';
        $this->assertSame('', tg_user_file('../../../uploads/x', 'state', $dir));
        $this->assertSame('', tg_user_file(777, 'evil', $dir));
    }

    public function testUserFileUsesAbsolutePathByDefault(): void
    {
        // Путь не должен зависеть от рабочего каталога процесса
        $file = tg_user_file(42, 'data');
        $this->assertNotSame('', $file);
        $this->assertStringStartsWith(tg_users_dir(), $file);
        $this->assertStringEndsWith('42_data.json', $file);
    }

    // --- Очистка устаревших состояний --------------------------------------

    public function testCleanupRemovesOnlyStaleFiles(): void
    {
        $dir = sys_get_temp_dir() . '/tg_cleanup_' . uniqid();
        mkdir($dir, 0777, true);

        $old = $dir . '/111_state.txt';
        $oldData = $dir . '/111_data.json';
        $fresh = $dir . '/222_state.txt';
        $other = $dir . '/readme.md';

        foreach ([$old, $oldData, $fresh, $other] as $f) {
            file_put_contents($f, 'x');
        }
        $now = time();
        touch($old, $now - 40 * 86400);
        touch($oldData, $now - 40 * 86400);
        touch($fresh, $now - 1 * 86400);
        touch($other, $now - 400 * 86400);

        $removed = tg_cleanup_users($dir, 30, $now);

        $this->assertSame(2, $removed);
        $this->assertFileDoesNotExist($old);
        $this->assertFileDoesNotExist($oldData);
        $this->assertFileExists($fresh);
        $this->assertFileExists($other);

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testCleanupSafeOnMissingDirectory(): void
    {
        $this->assertSame(0, tg_cleanup_users(sys_get_temp_dir() . '/нет_такого_каталога_tg', 30));
    }
}
