<?php
// /admin/includes/security_config.php

// Настройки безопасности для админки
if (!defined('ENABLE_IP_CHECK')) {
    define('ENABLE_IP_CHECK', true);
}

if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800); // 30 минут в секундах
}

// Порог блокировки учётной записи (admins.failed_attempts → locked_until)
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}

// Отдельный, более щадящий порог троттлинга по IP: за одним адресом может
// сидеть несколько сотрудников, поэтому он не должен запирать учётные записи
if (!defined('MAX_LOGIN_ATTEMPTS_IP')) {
    define('MAX_LOGIN_ATTEMPTS_IP', 15);
}

if (!defined('LOGIN_ATTEMPT_TIMEOUT')) {
    define('LOGIN_ATTEMPT_TIMEOUT', 900); // 15 минут в секундах
}

if (!defined('REQUIRE_HTTPS')) {
    define('REQUIRE_HTTPS', true);
}

// Допустимые роли пользователей.
// Совпадают с ENUM admins.role (миграция 2026-08-18-admin-roles.sql),
// с матрицей $PERMISSIONS и иерархией $ROLE_HIERARCHY в includes/permissions.php.
$ALLOWED_ROLES = [
    'superadmin',
    'admin',
    'manager',
    'viewer'
];

// Человекочитаемые названия ролей для интерфейса админки
$ROLE_LABELS = [
    'superadmin' => 'Суперадминистратор',
    'admin'      => 'Администратор',
    'manager'    => 'Менеджер',
    'viewer'     => 'Наблюдатель',
];

