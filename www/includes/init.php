<?php
// init.php - централизованная инициализация

// Загружаем конфигурацию
require_once __DIR__ . '/config.php';

// Загружаем функции (теперь они защищены от повторного объявления)
require_once __DIR__ . '/functions.php';

// Подключаем UTM трекер
require_once __DIR__ . '/utm_tracker.php';

// Инициализируем отслеживание UTM
UTMTracker::init();

// Запускаем сессию если она еще не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Обновляем время жизни сессии
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_LIFETIME)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();
?>