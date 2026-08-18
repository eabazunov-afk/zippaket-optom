<?php
// /admin/includes/permissions.php

/**
 * Иерархия ролей — единственный источник правды для сравнения «кто выше».
 * Используется и матрицей прав, и checkAdminRole() в auth.php,
 * и логикой управления учётными записями в users.php.
 */
$ROLE_HIERARCHY = [
    'superadmin' => 4,
    'admin'      => 3,
    'manager'    => 2,
    'viewer'     => 1,
    'guest'      => 0,
];

/**
 * Матрица разрешений по ролям
 */
$PERMISSIONS = [
    'superadmin' => [
        'view_leads' => true,
        'edit_leads' => true,
        'delete_leads' => true,
        'view_users' => true,
        'edit_users' => true,
        'delete_users' => true,
        'view_calculations' => true,
        'edit_calculations' => true,
        'view_settings' => true,
        'edit_settings' => true,
        'view_orders' => true,
        'edit_orders' => true,
        'view_reviews' => true,
        'moderate_reviews' => true,
        'view_statistics' => true,
        'export_data' => true,
        'view_logs' => true,
    ],
    'admin' => [
        'view_leads' => true,
        'edit_leads' => true,
        'delete_leads' => true,
        'view_users' => true,
        'edit_users' => false,
        'delete_users' => false,
        'view_calculations' => true,
        'edit_calculations' => true,
        'view_settings' => true,
        'edit_settings' => true,
        'view_orders' => true,
        'edit_orders' => true,
        'view_reviews' => true,
        'moderate_reviews' => true,
        'view_statistics' => true,
        'export_data' => true,
        'view_logs' => false,
    ],
    'manager' => [
        'view_leads' => true,
        'edit_leads' => true,
        'delete_leads' => false,
        'view_users' => false,
        'edit_users' => false,
        'delete_users' => false,
        'view_calculations' => true,
        'edit_calculations' => false,
        'view_settings' => false,
        'edit_settings' => false,
        'view_orders' => true,
        'edit_orders' => true,
        'view_reviews' => true,
        'moderate_reviews' => false,
        'view_statistics' => true,
        'export_data' => false,
        'view_logs' => false,
    ],
    'viewer' => [
        'view_leads' => true,
        'edit_leads' => false,
        'delete_leads' => false,
        'view_users' => false,
        'edit_users' => false,
        'delete_users' => false,
        'view_calculations' => true,
        'edit_calculations' => false,
        'view_settings' => false,
        'edit_settings' => false,
        'view_orders' => true,
        'edit_orders' => false,
        'view_reviews' => true,
        'moderate_reviews' => false,
        'view_statistics' => false,
        'export_data' => false,
        'view_logs' => false,
    ],
];

/**
 * Карта «страница → минимально необходимое право».
 * Deny by default: страница, которой здесь нет, недоступна никому.
 * Новую страницу админки обязательно добавлять сюда.
 */
$PAGE_PERMISSIONS = [
    'index.php'         => 'view_leads',
    'leads.php'         => 'view_leads',
    'lead_details.php'  => 'view_leads',
    'calculations.php'  => 'view_calculations',
    'settings.php'      => 'view_settings',
    'users.php'         => 'view_users',
    'orders.php'        => 'view_orders',
    'reviews.php'       => 'view_reviews',
    'statistics.php'    => 'view_statistics',
    'get_lead.php'      => 'view_leads', // api/get_lead.php
];

/**
 * Уровень роли в иерархии. Неизвестная роль = 0 (ниже всех).
 */
function roleLevel($role) {
    global $ROLE_HIERARCHY;
    return $ROLE_HIERARCHY[(string)$role] ?? 0;
}

/**
 * Может ли роль $actor_role управлять учётной записью с ролью $target_role.
 * Разрешаем равный или более низкий уровень: superadmin правит админов,
 * admin админов и ниже, но не суперадмина.
 */
function canManageRole($actor_role, $target_role) {
    return roleLevel($actor_role) >= roleLevel($target_role);
}

/**
 * Может ли роль $actor_role назначить роль $new_role.
 * Выдать роль выше собственной нельзя (в том числе себе).
 */
function canAssignRole($actor_role, $new_role) {
    global $ROLE_HIERARCHY;
    if (!isset($ROLE_HIERARCHY[(string)$new_role]) || $new_role === 'guest') {
        return false; // несуществующая или служебная роль
    }
    return roleLevel($new_role) <= roleLevel($actor_role);
}

/**
 * Проверка разрешения
 */
function checkPermission($permission) {
    global $PERMISSIONS;

    $role = $_SESSION['admin_role'] ?? 'viewer';

    if (!isset($PERMISSIONS[$role])) {
        return false;
    }

    return $PERMISSIONS[$role][$permission] ?? false;
}

/**
 * Проверка разрешения с редиректом при отказе
 */
function requirePermission($permission) {
    if (!checkPermission($permission)) {
        logAttempt($_SESSION['admin_username'] ?? 'unknown', 'permission_denied:' . $permission, false);
        header('HTTP/1.1 403 Forbidden');
        exit('Доступ запрещен. Недостаточно прав.');
    }
}

/**
 * Проверка доступа к странице на основе URL.
 * Страница, не описанная в $PAGE_PERMISSIONS, закрыта (deny by default).
 */
function checkPageAccess($page) {
    global $PAGE_PERMISSIONS;

    $page = basename((string)$page);

    if (!isset($PAGE_PERMISSIONS[$page])) {
        logAttempt($_SESSION['admin_username'] ?? 'unknown', 'page_not_allowed:' . $page, false);
        header('HTTP/1.1 403 Forbidden');
        exit('Доступ запрещен. Страница не описана в матрице прав.');
    }

    requirePermission($PAGE_PERMISSIONS[$page]);
}
