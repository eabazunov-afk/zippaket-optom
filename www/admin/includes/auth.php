<?php
// /admin/includes/auth.php

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
        header('HTTP/1.1 403 Forbidden');
        header('Location: /admin/login.php?error=session_expired');
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
    
    $role_hierarchy = [
        'superadmin' => 4,
        'admin' => 3,
        'manager' => 2,
        'viewer' => 1,
        'guest' => 0
    ];
    
    $current_level = $role_hierarchy[$current_role] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
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
 * Проверка лимита попыток входа для админки
 */
function checkAdminBruteForce($username, $ip) {
    try {
        // Получаем соединение с БД
        $db = getDbConnection();
        
        $time_window = 15 * 60; // 15 минут
        $max_attempts = 5;
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND success = 0
        ");
        $stmt->execute([$username, $ip, $time_window / 60]);
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