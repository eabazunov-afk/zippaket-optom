<?php
// tg/bot.php - БЕЗ КОНФЛИКТА ФУНКЦИЙ
// Подключаем конфигурацию бота
require_once __DIR__ . '/config.php';

// Подключаем основной конфиг сайта
require_once __DIR__ . '/../includes/config.php';

// Включаем вывод ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Создаем папку для хранения состояний если её нет
if (!is_dir('users')) {
    @mkdir('users', 0777, true);
}

// Функция логирования с детальной информацией
function log_message($message, $data = null) {
    $log_entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    if ($data) {
        $log_entry .= "Data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
    $log_entry .= "----------------------------------------\n";
    file_put_contents('bot_debug.log', $log_entry, FILE_APPEND);
}

// Получаем данные от Telegram
$content = file_get_contents('php://input');
$data = json_decode($content, true);

// Логируем входящий запрос
if ($content) {
    log_message("Входящий запрос от Telegram", $data);
}

// Если данных нет - это может быть проверка от сервера
if (!$data) {
    // Для отладки можно проверить в браузере
    if (isset($_GET['test'])) {
        echo "<pre>";
        echo "=== ТЕСТ БОТА ===\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n";
        echo "PHP Version: " . phpversion() . "\n";
        
        // Проверяем папку users
        echo "Users folder: " . (is_dir('users') ? '✅ Exists' : '❌ Missing') . "\n";
        
        // Проверяем конфиг
        echo "BOT_TOKEN: " . (defined('BOT_TOKEN') && BOT_TOKEN ? '✅ Set' : '❌ Not set') . "\n";
        if (defined('BOT_TOKEN') && BOT_TOKEN) {
            echo "Token length: " . strlen(BOT_TOKEN) . " chars\n";
            // Маскируем токен для безопасности
            $masked_token = substr(BOT_TOKEN, 0, 10) . '...' . substr(BOT_TOKEN, -5);
            echo "Token (masked): " . $masked_token . "\n";
        }
        
        echo "ADMIN_CHAT_ID: " . (defined('ADMIN_CHAT_ID') && ADMIN_CHAT_ID ? '✅ ' . ADMIN_CHAT_ID : '❌ Not set') . "\n";
        
        // Проверяем доступ к Telegram API
        if (defined('BOT_TOKEN') && BOT_TOKEN) {
            echo "\n=== ПРОВЕРКА TELEGRAM API ===\n";
            $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getMe";
            $response = @file_get_contents($url);
            if ($response) {
                $result = json_decode($response, true);
                if ($result['ok']) {
                    echo "✅ Бот подключен: @" . $result['result']['username'] . "\n";
                    echo "Bot name: " . $result['result']['first_name'] . "\n";
                    echo "Bot ID: " . $result['result']['id'] . "\n";
                } else {
                    echo "❌ Ошибка Telegram API: " . $result['description'] . "\n";
                }
            } else {
                echo "❐ Не удалось подключиться к Telegram API\n";
            }
        }
        
        // Проверяем запись в файлы
        echo "\n=== ПРОВЕРКА ЗАПИСИ ===\n";
        $test_file = 'users/test_write.txt';
        if (file_put_contents($test_file, 'Test write at ' . date('H:i:s'))) {
            echo "✅ Запись в файлы работает\n";
            unlink($test_file);
        } else {
            echo "❌ Ошибка записи в файлы\n";
        }
        
        echo "\n=== ТЕСТ ОТПРАВКИ СООБЩЕНИЙ ===\n";
        echo "Чтобы отправить тестовое сообщение, добавьте: &send=1\n";
        if (isset($_GET['send'])) {
            echo "Отправка тестового сообщения...\n";
            $test_message = "✅ Тестовое сообщение от бота\n" . 
                           "Время: " . date('d.m.Y H:i:s') . "\n" .
                           "Сервер: " . $_SERVER['HTTP_HOST'];
            $result = sendTelegramMessage(ADMIN_CHAT_ID, $test_message);
            echo "Результат: " . ($result ? '✅ Отправлено' : '❌ Ошибка') . "\n";
        }
        
        echo "\n=== ДОСТУПНЫЕ КОМАНДЫ ===\n";
        echo "• /start - Начать диалог\n";
        echo "• /order - Создать заявку\n";
        echo "• /test - Тестовая команда\n";
        
        echo "</pre>";
    }
    exit;
}

// Основная обработка сообщений
if (isset($data['message'])) {
    $message = $data['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $user_id = $message['from']['id'];
    $username = $message['from']['username'] ?? '';
    $first_name = $message['from']['first_name'] ?? '';
    $last_name = $message['from']['last_name'] ?? '';
    
    log_message("Сообщение от пользователя", [
        'user_id' => $user_id,
        'username' => $username,
        'chat_id' => $chat_id,
        'text' => $text
    ]);
    
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
        // Тестовая команда - отправляет уведомление админу
        $test_message = "🔄 Тестовое уведомление от пользователя:\n" .
                       "👤 ID: {$user_id}\n" .
                       "📝 Имя: {$first_name}\n" .
                       "🔗 @{$username}\n" .
                       "⏰ Время: " . date('H:i:s');
        
        sendTelegramMessage($chat_id, "Отправляю тестовое уведомление администратору...");
        
        // Тест уведомления админу
        $test_result = notifyTelegramAdmin(999, "Тестовый пользователь", "+79999999999", "Тестовый запрос от {$first_name} (@{$username})", $username);
        
        if ($test_result) {
            sendTelegramMessage($chat_id, "✅ Тестовое уведомление отправлено администратору!");
        } else {
            sendTelegramMessage($chat_id, "❌ Ошибка отправки уведомления. Проверьте логи.");
        }
        
    } elseif ($user_state === 'waiting_name') {
        // Пользователь ввел имя
        if (strlen($text) >= 2) {
            saveUserData($user_id, 'name', $text);
            setUserState($user_id, 'waiting_phone');
            
            sendTelegramMessage($chat_id, "✅ Отлично, {$text}!\n\nТеперь введите ваш номер телефона:");
            
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
    if (strlen($text) >= 5) {
        // Получаем сохраненные данные
        $user_data = getUserData($user_id);
        $name = $user_data['name'] ?? $first_name;
        $phone = $user_data['phone'] ?? '';
        
        // СОХРАНЯЕМ ЗАЯВКУ
        $lead_id = saveTelegramLead($name, $phone, $text, $user_id, $username);
        
        // ДОБАВИТЬ ОТЛАДКУ ЗДЕСЬ:
        file_put_contents('debug_lead.txt', 
            date('Y-m-d H:i:s') . " - Lead created\n" .
            "Lead ID: " . ($lead_id ?: 'NULL') . "\n" .
            "Name: $name\n" .
            "Phone: $phone\n" .
            "Comment: $text\n" .
            "User ID: $user_id\n" .
            "Username: $username\n\n",
            FILE_APPEND
        );
        
        if ($lead_id) {
            sendTelegramMessage($chat_id, 
                "🎉 Заявка #{$lead_id} создана!\n\n" .
                "Наш менеджер свяжется с вами в ближайшее время.\n\n" .
                "Для новой заявки используйте /order"
            );
            
            // ТЕСТ УВЕДОМЛЕНИЯ - ДОБАВИТЬ
            file_put_contents('debug_notify.txt', 
                date('Y-m-d H:i:s') . " - Calling notifyTelegramAdmin\n" .
                "Lead ID: $lead_id\n" .
                "Name: $name\n" .
                "Phone: $phone\n" .
                "Username: $username\n",
                FILE_APPEND
            );
            
            // Уведомляем админа
            $notify_result = notifyTelegramAdmin($lead_id, $name, $phone, $text, $username);
            
            // ЛОГИРУЕМ РЕЗУЛЬТАТ
            file_put_contents('debug_notify.txt', 
                "Notify result: " . ($notify_result ? 'SUCCESS' : 'FAILED') . "\n\n",
                FILE_APPEND
            );
            
            // Сбрасываем состояние
            setUserState($user_id, 'waiting_command');
            clearUserData($user_id);
        } else {
            sendTelegramMessage($chat_id, "❌ Ошибка сохранения. Попробуйте позже.");
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
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $contact = $message['contact'];
    $phone = $contact['phone_number'];
    $username = $message['from']['username'] ?? '';
    
    log_message("Получен контакт", [
        'user_id' => $user_id,
        'phone' => $phone,
        'chat_id' => $chat_id
    ]);
    
    $user_state = getUserState($user_id);
    
    if ($user_state === 'waiting_phone') {
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

// Функция отправки сообщения в Telegram (с улучшенной обработкой ошибок)
function sendTelegramMessage($chat_id, $text, $keyboard = null) {
    if (!defined('BOT_TOKEN') || empty(BOT_TOKEN)) {
        log_message("Ошибка: BOT_TOKEN не определен");
        return false;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    log_message("Отправка сообщения", [
        'chat_id' => $chat_id,
        'text_length' => strlen($text),
        'url' => str_replace(BOT_TOKEN, '***MASKED***', $url)
    ]);
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ];
    
    try {
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            $error = error_get_last();
            log_message("Ошибка отправки сообщения", $error);
            return false;
        }
        
        $result = json_decode($response, true);
        log_message("Ответ от Telegram API", $result);
        
        return $result['ok'] ?? false;
        
    } catch (Exception $e) {
        log_message("Исключение при отправке", ['error' => $e->getMessage()]);
        return false;
    }
}



// Функция уведомления админа
function notifyTelegramAdmin($lead_id, $name, $phone, $comment, $username) {
    // Логируем начало
    file_put_contents('notify_log.txt', 
        date('Y-m-d H:i:s') . " - notifyTelegramAdmin called\n" .
        "Lead ID: $lead_id\n" .
        "Name: $name\n" .
        "Phone: $phone\n" .
        "Username: $username\n" .
        "Comment length: " . strlen($comment) . "\n",
        FILE_APPEND
    );
    
    // Проверяем конфиг
    if (!defined('ADMIN_CHAT_ID') || empty(ADMIN_CHAT_ID)) {
        file_put_contents('notify_log.txt', 
            "ERROR: ADMIN_CHAT_ID not defined or empty\n\n",
            FILE_APPEND
        );
        return false;
    }
    
    if (!defined('BOT_TOKEN') || empty(BOT_TOKEN)) {
        file_put_contents('notify_log.txt', 
            "ERROR: BOT_TOKEN not defined or empty\n\n",
            FILE_APPEND
        );
        return false;
    }
    
    // Подготавливаем текст
    $text = "📨 <b>НОВАЯ ЗАЯВКА ИЗ ТЕЛЕГРАМА!</b>\n\n" .
            "🔢 <b>ID:</b> #{$lead_id}\n" .
            "👤 <b>Имя:</b> {$name}\n" .
            "📱 <b>Телефон:</b> {$phone}\n" .
            "🔗 <b>Telegram:</b> @" . ($username ?: 'не указан') . "\n\n" .
            "💬 <b>Запрос:</b>\n" . htmlspecialchars($comment) . "\n\n" .
            "⏰ <b>Время:</b> " . date('d.m.Y H:i:s');
    
    // Логируем текст
    file_put_contents('notify_log.txt', 
        "Text prepared, length: " . strlen($text) . "\n" .
        "Admin chat ID: " . ADMIN_CHAT_ID . "\n",
        FILE_APPEND
    );
    
    // Отправляем сообщение
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => ADMIN_CHAT_ID,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    
    try {
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            file_put_contents('notify_log.txt', 
                "ERROR: Failed to send request\n\n",
                FILE_APPEND
            );
            return false;
        }
        
        $result = json_decode($response, true);
        
        file_put_contents('notify_log.txt', 
            "Telegram API response: " . json_encode($result) . "\n\n",
            FILE_APPEND
        );
        
        return $result['ok'] ?? false;
        
    } catch (Exception $e) {
        file_put_contents('notify_log.txt', 
            "EXCEPTION: " . $e->getMessage() . "\n\n",
            FILE_APPEND
        );
        return false;
    }
}

// Функция начала заявки
function startOrder($chat_id, $user_id) {
    clearUserData($user_id);
    sendTelegramMessage($chat_id, "📝 Создание заявки\n\nШаг 1/3\nВведите ваше имя:");
    setUserState($user_id, 'waiting_name');
}

// Функция сохранения заявки из Telegram
function saveTelegramLead($name, $phone, $comment, $telegram_user_id, $telegram_username) {
    try {
        // Если нет доступа к БД, просто возвращаем случайный ID
        if (!function_exists('getDbConnection')) {
            $lead_id = rand(1000, 9999);
            log_message("БД недоступна, создан тестовый ID", ['lead_id' => $lead_id]);
            return $lead_id;
        }
        
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            INSERT INTO leads (name, phone, comment, status, source, parameters) 
            VALUES (?, ?, ?, 'new', 'telegram_bot', ?)
        ");
        
        $parameters = json_encode([
            'telegram_user_id' => $telegram_user_id,
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
        log_message("Заявка сохранена в БД", ['lead_id' => $lead_id]);
        
        return $lead_id;
        
    } catch (Exception $e) {
        // Если ошибка БД, все равно возвращаем ID для теста
        $lead_id = rand(1000, 9999);
        log_message("Ошибка БД, но возвращаем тестовый ID", [
            'error' => $e->getMessage(),
            'lead_id' => $lead_id
        ]);
        return $lead_id;
    }
}

// Функции для хранения состояний
function getUserState($user_id) {
    $file = "users/{$user_id}_state.txt";
    return file_exists($file) ? trim(file_get_contents($file)) : '';
}

function setUserState($user_id, $state) {
    $file = "users/{$user_id}_state.txt";
    file_put_contents($file, $state);
    log_message("Установлено состояние пользователя", [
        'user_id' => $user_id,
        'state' => $state
    ]);
}

function saveUserData($user_id, $key, $value) {
    $file = "users/{$user_id}_data.json";
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $data[$key] = $value;
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

function getUserData($user_id) {
    $file = "users/{$user_id}_data.json";
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function clearUserData($user_id) {
    $files = ["users/{$user_id}_state.txt", "users/{$user_id}_data.json"];
    foreach ($files as $file) {
        if (file_exists($file)) @unlink($file);
    }
    log_message("Данные пользователя очищены", ['user_id' => $user_id]);
}

// Отправляем успешный ответ Telegram
http_response_code(200);
log_message("Скрипт завершен успешно");
?>