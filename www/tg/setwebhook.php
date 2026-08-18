<?php
// tg/setwebhook.php — регистрация вебхука в Telegram.
//
// Скрипт служебный и НЕ должен быть доступен анониму: раньше любой мог
// переставить вебхук бота. Теперь требуется тот же секрет, что и у вебхука
// (TG_WEBHOOK_SECRET) — из заголовка X-Telegram-Bot-Api-Secret-Token или
// параметра ?secret=... . Запуск из CLI разрешён без секрета.
//
// Использование:
//   php www/tg/setwebhook.php
//   curl "https://<домен>/tg/setwebhook.php?secret=<TG_WEBHOOK_SECRET>"

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot_lib.php';
require_once __DIR__ . '/../includes/notify/telegram_notify.php';

// includes/config.php здесь не подключается — задаём зону явно, чтобы
// временные метки в логе совпадали с метками бота.
date_default_timezone_set('Europe/Moscow');

$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    header('Content-Type: text/plain; charset=utf-8');

    if (!tg_secret_valid(tg_request_secret(true), defined('TG_WEBHOOK_SECRET') ? TG_WEBHOOK_SECRET : null)) {
        http_response_code(403);
        tg_log('setwebhook: доступ запрещён — неверный секрет');
        exit;
    }
}

if (!defined('BOT_TOKEN') || BOT_TOKEN === '' || !defined('TG_WEBHOOK_SECRET') || TG_WEBHOOK_SECRET === '') {
    http_response_code(500);
    echo "Не заданы BOT_TOKEN и/или TG_WEBHOOK_SECRET в tg/config.php\n";
    exit;
}

$webhook_url = defined('TG_WEBHOOK_URL') && TG_WEBHOOK_URL !== ''
    ? TG_WEBHOOK_URL
    : 'https://zippaket-optom.ru/tg/bot.php';

$url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/setWebhook';

// secret_token — Telegram будет присылать его в заголовке
// X-Telegram-Bot-Api-Secret-Token, bot.php сверяет его через hash_equals.
$post = [
    'url' => $webhook_url,
    'secret_token' => TG_WEBHOOK_SECRET,
    'drop_pending_updates' => 'true',
];

try {
    // Тот же транспорт, что и у бота: cURL с проверкой TLS-сертификата.
    $resp = telegram_curl_post($url, $post);
} catch (Throwable $e) {
    http_response_code(502);
    echo "Ошибка обращения к Telegram API: " . $e->getMessage() . "\n";
    exit;
}

$result = json_decode((string)($resp['body'] ?? ''), true);

echo "Webhook URL: {$webhook_url}\n";
echo "HTTP: " . ($resp['status'] ?? '?') . "\n";
echo "Result: " . (is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : 'нет ответа') . "\n";
