<?php
require_once '../includes/init.php';
require_once 'includes/security_config.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php'; // Добавьте эту строку

// Проверка авторизации
checkAdminAuth();

// Проверка доступа к этой странице
$current_page = basename($_SERVER['PHP_SELF']);
checkPageAccess($current_page);

// Для конкретных действий в файле:
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    requirePermission('delete_leads');
}