<?php
// /admin/includes/permissions.php

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
        'edit_settings' => false,
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
        'export_data' => false,
        'view_logs' => false,
    ],
];

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
 * Проверка доступа к странице на основе URL
 */
function checkPageAccess($page) {
    $page_permissions = [
        'index.php' => 'view_leads',
        'leads.php' => 'view_leads',
        'lead_details.php' => 'view_leads',
        'calculations.php' => 'view_calculations',
        'settings.php' => 'view_settings',
        'users.php' => 'view_users',
    ];
    
    if (isset($page_permissions[$page])) {
        requirePermission($page_permissions[$page]);
    }
}
