<?php
/**
 * Отправка сообщения в Telegram администратору.
 * Токен и chat_id берутся из www/tg/config.php (BOT_TOKEN, ADMIN_CHAT_ID).
 * HTTP-клиент инъектируется (для тестов); по умолчанию — cURL.
 *
 * Клиент: function(string $url, array $postFields): array{status:int, body:string}
 */

/** Подключить креды бота, если ещё не подключены. */
function telegram_load_config(): void
{
    if (!defined('BOT_TOKEN')) {
        $cfg = __DIR__ . '/../../tg/config.php';
        if (is_file($cfg)) {
            require_once $cfg;
        }
    }
}

/** Настроен ли Telegram-бот. */
function telegram_configured(): bool
{
    telegram_load_config();
    return defined('BOT_TOKEN') && BOT_TOKEN !== '' && defined('ADMIN_CHAT_ID') && (string)ADMIN_CHAT_ID !== '';
}

/**
 * Отправить текст. Возвращает true при успехе. Никогда не бросает исключений.
 * @param callable|null $http function(string $url, array $post): array{status:int,body:string}
 */
function telegram_send(string $text, ?string $chatId = null, ?callable $http = null): bool
{
    if (!telegram_configured()) {
        return false;
    }
    $chatId = $chatId ?? (string)ADMIN_CHAT_ID;
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $post = ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => true];

    try {
        $client = $http ?? 'telegram_curl_post';
        $resp = $client($url, $post);
        $ok = ($resp['status'] ?? 0) === 200;
        if (!$ok) {
            error_log('telegram_send failed: HTTP ' . ($resp['status'] ?? '?') . ' ' . ($resp['body'] ?? ''));
        }
        return $ok;
    } catch (Throwable $e) {
        error_log('telegram_send error: ' . $e->getMessage());
        return false;
    }
}

/** cURL-клиент по умолчанию. */
function telegram_curl_post(string $url, array $post): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Telegram cURL: ' . $err);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => (string)$body];
}
