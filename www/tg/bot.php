<?php
// tg/bot.php — вебхук Telegram-бота (публичный эндпоинт).
//
// Апдейты принимаются ТОЛЬКО от Telegram: обязателен секретный токен
// (заголовок X-Telegram-Bot-Api-Secret-Token, константа TG_WEBHOOK_SECRET,
// регистрируется в setwebhook.php). Без валидного секрета — 403 без деталей.

// Конфигурация бота: BOT_TOKEN, ADMIN_CHAT_ID, TG_WEBHOOK_SECRET
require_once __DIR__ . '/config.php';

// Основной конфиг сайта: LOG_DIR, getDbConnection()
require_once __DIR__ . '/../includes/config.php';

// Вспомогательные функции бота (проверка секрета, пути, лог)
require_once __DIR__ . '/bot_lib.php';

// Единый отправитель в Telegram (cURL с проверкой TLS) — не плодим копий
require_once __DIR__ . '/../includes/notify/telegram_notify.php';

// Публичный эндпоинт: ошибки уходят только в лог, наружу ничего не печатаем.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// --- Проверка подлинности запроса -------------------------------------------

// Вебхук работает только на POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(403);
    exit;
}

// Секретный токен Telegram (сравнение через hash_equals, fail-closed)
if (!tg_secret_valid(tg_request_secret(), defined('TG_WEBHOOK_SECRET') ? TG_WEBHOOK_SECRET : null)) {
    http_response_code(403);
    tg_log('webhook: запрос отклонён — неверный или отсутствующий секретный токен');
    exit;
}

// --- Разбор апдейта ---------------------------------------------------------

$content = file_get_contents('php://input');
$data = json_decode((string)$content, true);

if (!is_array($data)) {
    http_response_code(200);
    tg_log('webhook: пустое или некорректное тело запроса');
    exit;
}

// Чистим устаревшие файлы состояний (в них лежат имя и телефон)
tg_cleanup_users(tg_users_dir_ensure(), TG_STATE_TTL_DAYS);

// Основная обработка сообщений
if (isset($data['message'])) {
    $message = $data['message'];
    $chat_id = $message['chat']['id'] ?? null;
    $text = $message['text'] ?? '';
    $user_id = tg_user_id($message['from']['id'] ?? null);
    $username = $message['from']['username'] ?? '';
    $first_name = $message['from']['first_name'] ?? '';

    // Некорректный идентификатор — обрабатывать нечего
    if ($user_id === 0 || $chat_id === null) {
        http_response_code(200);
        tg_log('message: отбой — некорректный user_id или chat_id');
        exit;
    }

    tg_log('message: обработка апдейта, user_id=' . $user_id);

    // Получаем текущее состояние пользователя
    $user_state = getUserState($user_id);

    // Обработка команд
    if ($text === '/start') {
        $welcome_message = "👋 <b>Добро пожаловать в бот ZLOCK-Зип пакеты оптом!</b>\n\n" .
            "🚀 <b>Мы производим:</b>\n" .
            "•Зип-пакеты с бегунком\n" .
            "• Зип-лок пакеты\n" .
            "• Пакеты с гриппером\n" .
            "📦 <b>Преимущества:</b>\n" .
            "✅ Собственное производство\n" .
            "✅ Любые размеры и толщины\n" .
            "✅ Печать логотипа\n" .
            "✅ Доставка по всей России\n\n" .
            "💡 <b>Для расчета стоимости и заказа:</b>\n" .
            "1. Используйте команду /order\n" .
            "2. Ответьте на 3 простых вопроса\n" .
            "3. Получите расчет в течение 15 минут\n\n" .
            "📞 <b>Контакты:</b>\n" .
            "Телефон: +7 (920) 346-50-67\n" .
            "Email: ZTR37@Bk.ru\n\n" .
            "👇 <b>Начните с команды:</b> /order";

        sendTelegramMessage($chat_id, $welcome_message);
        setUserState($user_id, 'waiting_command');

    } elseif ($text === '/order') {
        startOrder($chat_id, $user_id);

    } elseif ($text === '/test') {
        // Служебная команда: доступна только администратору,
        // иначе любой пользователь мог бы слать уведомления админу.
        if (defined('ADMIN_CHAT_ID') && (string)$user_id === (string)ADMIN_CHAT_ID) {
            $ok = notifyTelegramAdmin(0, 'Тестовый пользователь', '+79999999999', 'Тестовый запрос', $username);
            sendTelegramMessage(
                $chat_id,
                $ok ? "✅ Тестовое уведомление отправлено администратору!"
                    : "❌ Ошибка отправки уведомления. Проверьте логи."
            );
        } else {
            sendTelegramMessage($chat_id, "Напишите /start для начала или /order для создания заявки");
        }

    } elseif ($user_state === 'waiting_name') {
        // Пользователь ввел имя
        if (mb_strlen($text) >= 2) {
            saveUserData($user_id, 'name', $text);
            setUserState($user_id, 'waiting_phone');

            sendTelegramMessage($chat_id, "✅ Отлично, " . htmlspecialchars($text) . "!\n\nТеперь введите ваш номер телефона:");

        } else {
            sendTelegramMessage($chat_id, "❌ Имя должно содержать минимум 2 символа. Попробуйте снова:");
        }

    } elseif ($user_state === 'waiting_phone') {
        // Пользователь ввел телефон
        $phone = preg_replace('/[^0-9+]/', '', $text);
        if (strlen($phone) >= 10) {
            saveUserData($user_id, 'phone', $phone);
            setUserState($user_id, 'waiting_comment');

            sendTelegramMessage($chat_id,
                "✅ Телефон принят!\n\n" .
                "Теперь опишите что вам нужно:\n" .
                "• Тип пакетов\n" .
                "• Размеры\n" .
                "• Количество\n" .
                "• Особые пожелания"
            );
        } else {
            sendTelegramMessage($chat_id, "❌ Неверный формат телефона. Введите еще раз:");
        }

    } elseif ($user_state === 'waiting_comment') {
        // Пользователь ввел комментарий
        if (mb_strlen($text) >= 5) {
            // Получаем сохраненные данные
            $user_data = getUserData($user_id);
            $name = $user_data['name'] ?? $first_name;
            $phone = $user_data['phone'] ?? '';

            // СОХРАНЯЕМ ЗАЯВКУ
            $lead_id = saveTelegramLead($name, $phone, $text, $user_id, $username);

            if ($lead_id) {
                sendTelegramMessage($chat_id,
                    "🎉 Заявка #{$lead_id} создана!\n\n" .
                    "Наш менеджер свяжется с вами в ближайшее время.\n\n" .
                    "Для новой заявки используйте /order"
                );

                // Уведомляем админа
                $notify_result = notifyTelegramAdmin($lead_id, $name, $phone, $text, $username);
                tg_log('lead: уведомление админу — ' . ($notify_result ? 'отправлено' : 'ошибка') . ', lead_id=' . $lead_id);

                // Сбрасываем состояние
                setUserState($user_id, 'waiting_command');
                clearUserData($user_id);
            } else {
                // Заявка НЕ сохранена — честно сообщаем об ошибке,
                // выдуманный номер пользователю не показываем.
                sendTelegramMessage($chat_id,
                    "❌ Не удалось сохранить заявку. Попробуйте позже " .
                    "или позвоните нам: +7 (920) 346-50-67"
                );
            }
        } else {
            sendTelegramMessage($chat_id, "❌ Описание слишком короткое. Напишите подробнее:");
        }

    } else {
        sendTelegramMessage($chat_id, "Напишите /start для начала или /order для создания заявки");
    }
}

// Обработка контакта
if (isset($data['message']['contact'])) {
    $message = $data['message'];
    $chat_id = $message['chat']['id'] ?? null;
    $user_id = tg_user_id($message['from']['id'] ?? null);
    $phone = $message['contact']['phone_number'] ?? '';

    if ($user_id > 0 && $chat_id !== null && getUserState($user_id) === 'waiting_phone') {
        tg_log('contact: получен контакт, user_id=' . $user_id);

        saveUserData($user_id, 'phone', $phone);
        setUserState($user_id, 'waiting_comment');

        sendTelegramMessage($chat_id,
            "✅ Телефон получен!\n\n" .
            "Теперь опишите что вам нужно:\n" .
            "• Тип пакетов\n" .
            "• Размеры\n" .
            "• Количество\n" .
            "• Особые пожелания"
        );
    }
}

/**
 * Отправка сообщения в Telegram.
 * Транспорт — telegram_curl_post() из includes/notify/telegram_notify.php:
 * cURL со штатной проверкой TLS-сертификата (токен идёт в URL, отключать
 * проверку нельзя — иначе MITM получает токен бота).
 */
function sendTelegramMessage($chat_id, $text, $keyboard = null)
{
    if (!defined('BOT_TOKEN') || BOT_TOKEN === '') {
        tg_log('send: BOT_TOKEN не определён');
        return false;
    }

    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $post = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    if ($keyboard) {
        $post['reply_markup'] = json_encode($keyboard);
    }

    try {
        $resp = telegram_curl_post($url, $post);
        $ok = ($resp['status'] ?? 0) === 200;
        if (!$ok) {
            // В лог — только код ответа, без текста сообщения (ПДн)
            tg_log('send: Telegram API вернул HTTP ' . ($resp['status'] ?? '?'));
        }
        return $ok;
    } catch (Throwable $e) {
        tg_log('send: ошибка транспорта — ' . $e->getMessage());
        return false;
    }
}

/** Уведомление админа о новой заявке. */
function notifyTelegramAdmin($lead_id, $name, $phone, $comment, $username)
{
    if (!defined('ADMIN_CHAT_ID') || (string)ADMIN_CHAT_ID === '') {
        tg_log('notify: ADMIN_CHAT_ID не определён');
        return false;
    }

    $text = "📨 <b>НОВАЯ ЗАЯВКА ИЗ ТЕЛЕГРАМА!</b>\n\n" .
            "🔢 <b>ID:</b> #" . htmlspecialchars((string)$lead_id) . "\n" .
            "👤 <b>Имя:</b> " . htmlspecialchars((string)$name) . "\n" .
            "📱 <b>Телефон:</b> " . htmlspecialchars((string)$phone) . "\n" .
            "🔗 <b>Telegram:</b> @" . htmlspecialchars((string)($username ?: 'не указан')) . "\n\n" .
            "💬 <b>Запрос:</b>\n" . htmlspecialchars((string)$comment) . "\n\n" .
            "⏰ <b>Время:</b> " . date('d.m.Y H:i:s');

    return sendTelegramMessage(ADMIN_CHAT_ID, $text);
}

// Функция начала заявки
function startOrder($chat_id, $user_id)
{
    clearUserData($user_id);
    sendTelegramMessage($chat_id, "📝 Создание заявки\n\nШаг 1/3\nВведите ваше имя:");
    setUserState($user_id, 'waiting_name');
}

/**
 * Сохранение заявки из Telegram.
 * Возвращает id заявки либо false — «честный» результат: при ошибке БД
 * номер не выдумывается, пользователь получает сообщение об ошибке.
 */
function saveTelegramLead($name, $phone, $comment, $telegram_user_id, $telegram_username)
{
    if (!function_exists('getDbConnection')) {
        tg_log('lead: БД недоступна — getDbConnection() не определена, заявка не сохранена');
        return false;
    }

    try {
        $db = getDbConnection();

        $stmt = $db->prepare("
            INSERT INTO leads (name, phone, comment, status, source, parameters)
            VALUES (?, ?, ?, 'new', 'telegram_bot', ?)
        ");

        $parameters = json_encode([
            'telegram_user_id' => tg_user_id($telegram_user_id),
            'telegram_username' => $telegram_username,
            'created_at' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

        $stmt->execute([
            $name,
            $phone,
            $comment,
            $parameters
        ]);

        $lead_id = $db->lastInsertId();
        tg_log('lead: заявка сохранена, lead_id=' . $lead_id);

        return $lead_id;

    } catch (Throwable $e) {
        tg_log('lead: ошибка сохранения в БД — ' . $e->getMessage());
        return false;
    }
}

// --- Функции хранения состояний ---------------------------------------------
// Пути строятся от __DIR__ через tg_user_file(): id приводится к целому,
// имя файла — только цифры + суффикс из белого списка.

function getUserState($user_id)
{
    $file = tg_user_file($user_id, 'state');
    return ($file !== '' && is_file($file)) ? trim((string)file_get_contents($file)) : '';
}

function setUserState($user_id, $state)
{
    $file = tg_user_file($user_id, 'state');
    if ($file === '') {
        tg_log('state: отбой — некорректный user_id');
        return;
    }
    tg_users_dir_ensure();
    file_put_contents($file, $state);
}

function saveUserData($user_id, $key, $value)
{
    $file = tg_user_file($user_id, 'data');
    if ($file === '') {
        tg_log('data: отбой — некорректный user_id');
        return;
    }
    tg_users_dir_ensure();
    $data = is_file($file) ? (array)json_decode((string)file_get_contents($file), true) : [];
    $data[$key] = $value;
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

function getUserData($user_id)
{
    $file = tg_user_file($user_id, 'data');
    return ($file !== '' && is_file($file)) ? (array)json_decode((string)file_get_contents($file), true) : [];
}

function clearUserData($user_id)
{
    foreach (['state', 'data'] as $kind) {
        $file = tg_user_file($user_id, $kind);
        if ($file !== '' && is_file($file)) {
            @unlink($file);
        }
    }
}

// Отправляем успешный ответ Telegram
http_response_code(200);
