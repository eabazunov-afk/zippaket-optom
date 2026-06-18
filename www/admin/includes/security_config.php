<?php
// /admin/includes/security_config.php

// Настройки безопасности для админки
if (!defined('ENABLE_IP_CHECK')) {
    define('ENABLE_IP_CHECK', true);
}

if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800); // 30 минут в секундах
}

if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}

if (!defined('LOGIN_ATTEMPT_TIMEOUT')) {
    define('LOGIN_ATTEMPT_TIMEOUT', 900); // 15 минут в секундах
}

if (!defined('REQUIRE_HTTPS')) {
    define('REQUIRE_HTTPS', true);
}

// Допустимые роли пользователей
$ALLOWED_ROLES = [
    'superadmin',
    'admin', 
    'manager',
    'viewer'
];

