<?php
// ШАБЛОН конфигурации Telegram-бота. Скопировать в tg/config.php и заполнить
// реальными значениями. config.php НЕ коммитится (см. .gitignore) — здесь
// только плейсхолдеры вместо секретов.

// Токен бота от @BotFather.
define('BOT_TOKEN', 'ВАШ_BOT_TOKEN');

// Chat ID администратора, куда падают уведомления о заявках (@userinfobot).
define('ADMIN_CHAT_ID', 'ВАШ_ADMIN_CHAT_ID');

// Секрет вебхука. Передаётся в setWebhook как secret_token, Telegram шлёт его
// обратно в заголовке X-Telegram-Bot-Api-Secret-Token, bot.php сверяет через
// hash_equals. Без него вебхук отклоняет ВСЕ запросы (fail-closed).
// Сгенерировать: php -r "echo bin2hex(random_bytes(32));"
// Разрешены символы A-Z, a-z, 0-9, _ и -, длина 1..256.
define('TG_WEBHOOK_SECRET', 'ВАШ_TG_WEBHOOK_SECRET');

// URL вебхука (необязательно). Если не задан — используется
// https://zippaket-optom.ru/tg/bot.php (см. setwebhook.php).
define('TG_WEBHOOK_URL', 'https://zippaket-optom.ru/tg/bot.php');

// Срок хранения файлов состояний tg/users/ в днях (152-ФЗ: в них имя и
// телефон, бессрочно хранить нельзя). Необязательно, по умолчанию 30.
define('TG_STATE_TTL_DAYS', 30);
