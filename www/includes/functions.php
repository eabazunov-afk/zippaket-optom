<?php
/**
 * functions.php - вспомогательные функции
 * Проверка на повторное объявление функций
 */

// Проверяем, не был ли файл уже подключен
if (defined('FUNCTIONS_LOADED')) {
    return;
}

define('FUNCTIONS_LOADED', true);

/**
 * Проверка авторизации администратора
 */
function requireAdminAuth() {
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Проверка, авторизован ли администратор
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Проверка email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Проверка телефона
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10;
}


/**
 * Отправка email
 */
function sendEmail($to, $subject, $message, $from = ADMIN_EMAIL) {
    $headers = "From: " . $from . "\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Логирование действий
 */
function logAction($action, $data = []) {
    $logPath = defined('ROOT_PATH') ? ROOT_PATH . '/logs/admin.log' : dirname(__DIR__) . '/logs/admin.log';
    
    // Создаем папку logs если её нет
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('Y-m-d H:i:s') . " - $action: " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logPath, $log, FILE_APPEND);
}

/**
 * Генерация случайного ID
 */
function generateId($prefix = 'ID') {
    return $prefix . '-' . time() . '-' . bin2hex(random_bytes(4));
}

/**
 * Хеширование пароля
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Проверка пароля
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>