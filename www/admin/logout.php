<?php
/**
 * Выход из админ-панели
 */

require_once '../includes/init.php';
require_once 'includes/auth.php';

// Записываем действие в логи
if (isset($_SESSION['admin_id'])) {
    logAttempt($_SESSION['admin_username'] ?? 'unknown', 'logout', true);
}

// Очищаем все данные сессии
$_SESSION = [];

// Удаляем сессионную куку
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Удаляем remember me куку
setcookie('admin_remember', '', time() - 3600, '/admin/', '', true, true);

// Уничтожаем сессию
session_destroy();

// Редирект на страницу входа
header('Location: /admin/login.php');
exit;