<?php
// /admin/includes/auth.php

// Иерархия ролей и матрица прав живут в permissions.php — подключаем,
// чтобы checkAdminRole() и матрица пользовались одними и теми же уровнями.
require_once __DIR__ . '/permissions.php';

/**
 * Проверка администраторской сессии
 */
function checkAdminAuth() {
    global $db; // Добавляем доступ к глобальной переменной
    
    if (!$db) {
        $db = getDbConnection();
    }
    
    // Проверяем, запущена ли сессия
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_secure'   => true,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict'
        ]);
    }
    
    // Проверяем наличие ID администратора
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        logAttempt(null, 'session_missing', false);
        // 302, а не 403: с кодом 403 браузер игнорирует Location и показывает пустую страницу
        header('Location: /admin/login.php?error=session_expired', true, 302);
        exit;
    }
    
    // Проверяем IP-адрес (если включено)
    if (defined('ENABLE_IP_CHECK') && ENABLE_IP_CHECK && isset($_SESSION['admin_ip'])) {
        if ($_SESSION['admin_ip'] !== $_SERVER['REMOTE_ADDR']) {
            logAttempt($_SESSION['admin_username'] ?? 'unknown', 'ip_mismatch', false);
            session_destroy();
            header('Location: /admin/login.php?error=ip_changed');
            exit;
        }
    }
    
    // Проверяем время жизни сессии (30 минут)
    if (isset($_SESSION['admin_last_activity'])) {
        $session_lifetime = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : (30 * 60);
        if (time() - $_SESSION['admin_last_activity'] > $session_lifetime) {
            logAttempt($_SESSION['admin_username'] ?? 'unknown', 'session_expired', false);
            session_destroy();
            header('Location: /admin/login.php?error=session_expired');
            exit;
        }
    }
    
    // Обновляем время последней активности
    $_SESSION['admin_last_activity'] = time();
    
    return true;
}

/**
 * Проверка роли администратора
 */
function checkAdminRole($required_role = 'admin') {
    $current_role = $_SESSION['admin_role'] ?? 'guest';

    // Уровни берём из общей иерархии ($ROLE_HIERARCHY в permissions.php)
    $current_level = roleLevel($current_role);
    $required_level = roleLevel($required_role);

    if ($current_level < $required_level) {
        logAttempt($_SESSION['admin_username'] ?? 'unknown', 'insufficient_permissions', false);
        header('HTTP/1.1 403 Forbidden');
        exit('Доступ запрещен. Недостаточно прав.');
    }
    
    return true;
}

/**
 * Валидация CSRF токена
 */
function validateAdminCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        logAttempt($_SESSION['admin_username'] ?? 'unknown', 'csrf_failed', false);
        return false;
    }
    return true;
}

/**
 * Логирование попыток входа
 */
function logAttempt($username, $reason = '', $success = false) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    try {
        // Получаем соединение с БД
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            INSERT INTO login_attempts 
            (username, ip_address, user_agent, referer, success, reason, attempt_time) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $ip, $user_agent, $referer, $success ? 1 : 0, $reason]);
        
        return true;
    } catch (Exception $e) {
        // В случае ошибки логируем в файл
        error_log("Failed to log admin login attempt: " . $e->getMessage());
        
        // Альтернативное логирование в файл
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_entry = sprintf(
            "[%s] %s - %s - %s - %s - %s\n",
            date('Y-m-d H:i:s'),
            $ip,
            $username,
            $success ? 'SUCCESS' : 'FAILED',
            $reason,
            $user_agent
        );
        
        @file_put_contents($log_dir . 'admin_auth.log', $log_entry, FILE_APPEND);
        
        return false;
    }
}

/**
 * Причины из login_attempts, которые считаются неудачной попыткой подбора пароля.
 * Всё остальное (истёкшая сессия, отказ по правам, факт блокировки) счётчик
 * брутфорса не увеличивает — иначе блокировка сама себя продлевает.
 */
function bruteForceCountedReasons() {
    return ['user_not_found', 'wrong_password'];
}

/**
 * Проверка лимита попыток входа с одного IP.
 * Счётчик по учётной записи ведётся отдельно (admins.failed_attempts / locked_until),
 * чтобы чужие переборы не запирали конкретного администратора.
 */
function checkAdminBruteForce($ip) {
    try {
        // Получаем соединение с БД
        $db = getDbConnection();

        $window_minutes = (defined('LOGIN_ATTEMPT_TIMEOUT') ? LOGIN_ATTEMPT_TIMEOUT : 900) / 60;
        $max_attempts = defined('MAX_LOGIN_ATTEMPTS_IP') ? MAX_LOGIN_ATTEMPTS_IP : 15;

        $reasons = bruteForceCountedReasons();
        $placeholders = implode(',', array_fill(0, count($reasons), '?'));

        $stmt = $db->prepare("
            SELECT COUNT(*) as attempts
            FROM login_attempts
            WHERE ip_address = ?
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND success = 0
            AND reason IN ($placeholders)
        ");
        $stmt->execute(array_merge([$ip, $window_minutes], $reasons));
        $result = $stmt->fetch();

        return ($result && $result['attempts'] >= $max_attempts);
    } catch (Exception $e) {
        error_log("Brute force check failed: " . $e->getMessage());
        return false; // В случае ошибки не блокируем
    }
}

/**
 * Генерация безопасного токена для remember me
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}