<?php
// tg/bot_lib.php — вспомогательные функции Telegram-бота.
//
// Файл намеренно не имеет побочных эффектов при подключении (ничего не читает
// из php://input, не пишет ответов) — его можно подключать в тестах.
// Здесь сосредоточены проверки, от которых зависит безопасность вебхука:
// сверка секретного токена, нормализация идентификатора пользователя и
// построение путей к файлам состояний.

// Срок хранения файлов состояния (152-ФЗ: ПДн не хранятся бессрочно).
if (!defined('TG_STATE_TTL_DAYS')) {
    define('TG_STATE_TTL_DAYS', 30);
}

/**
 * Сверка секретного токена вебхука.
 *
 * Telegram присылает значение, заданное при setWebhook, в заголовке
 * X-Telegram-Bot-Api-Secret-Token. Сравнение — только hash_equals
 * (защита от тайминг-атак). Если секрет на сервере не настроен —
 * запрос отклоняется (fail-closed), иначе вебхук снова открыт всем.
 *
 * @param string|null $provided значение из заголовка запроса
 * @param string|null $expected значение из конфига (TG_WEBHOOK_SECRET)
 */
function tg_secret_valid(?string $provided, ?string $expected): bool
{
    if ($expected === null || $expected === '') {
        return false;
    }
    if ($provided === null || $provided === '') {
        return false;
    }
    return hash_equals($expected, $provided);
}

/**
 * Секрет из входящего запроса.
 * Основной источник — $_SERVER (mod_php/FPM), запасной — getallheaders()
 * на конфигурациях, где заголовок не попадает в $_SERVER.
 * Для setwebhook.php дополнительно допускается query-параметр secret.
 */
function tg_request_secret(bool $allow_query = false): ?string
{
    if (!empty($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])) {
        return (string)$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'];
    }
    if (function_exists('getallheaders')) {
        foreach ((array)getallheaders() as $name => $value) {
            if (strcasecmp((string)$name, 'X-Telegram-Bot-Api-Secret-Token') === 0) {
                return (string)$value;
            }
        }
    }
    if ($allow_query && isset($_GET['secret'])) {
        return (string)$_GET['secret'];
    }
    return null;
}

/**
 * Нормализация Telegram user_id из тела запроса.
 * Значение приходит от клиента, поэтому допускаются только целые
 * положительные числа: строка вида '../../../uploads/x' даёт 0 (отбой).
 *
 * @param mixed $raw
 * @return int корректный id или 0
 */
function tg_user_id($raw): int
{
    if (is_int($raw)) {
        return $raw > 0 ? $raw : 0;
    }
    if (is_string($raw) && ctype_digit($raw)) {
        $id = (int)$raw;
        return $id > 0 ? $id : 0;
    }
    return 0;
}

/** Каталог файлов состояний. Путь строится от __DIR__, а не от cwd. */
function tg_users_dir(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'users';
}

/** Каталог состояний с созданием при отсутствии. */
function tg_users_dir_ensure(): string
{
    $dir = tg_users_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

/**
 * Путь к файлу состояния/данных пользователя.
 * Имя файла собирается только из целого id и суффикса из белого списка,
 * поэтому выйти за пределы каталога подстановкой значения нельзя.
 *
 * @param mixed       $user_id сырое значение из запроса
 * @param string      $kind    'state' | 'data'
 * @param string|null $dir     каталог (по умолчанию tg_users_dir())
 * @return string путь или '' при некорректных аргументах
 */
function tg_user_file($user_id, string $kind, ?string $dir = null): string
{
    $id = tg_user_id($user_id);
    if ($id <= 0) {
        return '';
    }
    $suffix = ['state' => '_state.txt', 'data' => '_data.json'][$kind] ?? '';
    if ($suffix === '') {
        return '';
    }
    $dir = $dir ?? tg_users_dir();
    return rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . $id . $suffix;
}

/**
 * Удаление устаревших файлов состояний (в них хранятся имя и телефон).
 *
 * @param string   $dir      каталог состояний
 * @param int      $ttl_days срок хранения в днях
 * @param int|null $now      текущее время (для тестов)
 * @return int сколько файлов удалено
 */
function tg_cleanup_users(string $dir, int $ttl_days = TG_STATE_TTL_DAYS, ?int $now = null): int
{
    if ($ttl_days <= 0 || !is_dir($dir)) {
        return 0;
    }
    $now = $now ?? time();
    $deadline = $now - $ttl_days * 86400;
    $removed = 0;

    foreach (['*_state.txt', '*_data.json'] as $mask) {
        foreach ((array)glob(rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . $mask) as $file) {
            if (!is_file($file)) {
                continue;
            }
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $deadline && @unlink($file)) {
                $removed++;
            }
        }
    }

    return $removed;
}

/**
 * Технический лог бота — ВНЕ веб-корня (LOG_DIR из includes/config.php).
 * 152-ФЗ: в лог не пишем ПДн (имена, телефоны, тексты сообщений, тела
 * апдейтов) — только идентификаторы событий, коды ответов и ошибки.
 * Если каталог логов недоступен — откатываемся на системный error_log.
 */
function tg_log(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] tg-bot: ' . $message;

    $dir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents(
            rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . 'tg-bot.log',
            $line . PHP_EOL,
            FILE_APPEND
        );
        return;
    }

    error_log($line);
}
