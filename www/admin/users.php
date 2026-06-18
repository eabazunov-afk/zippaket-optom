<?php
require_once '../includes/init.php';
require_once 'includes/security_config.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';
require_once 'includes/audit.php';

// Проверка авторизации
checkAdminAuth();

// Проверка прав - только админы могут управлять пользователями
checkAdminRole('admin');

$db = getDbConnection();
$message = '';
$error = '';

// Обработка действий
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Проверка CSRF для действий
if ($action && in_array($action, ['create', 'edit', 'delete', 'toggle_status'])) {
    if (!isset($_GET['csrf_token']) || !validateAdminCsrfToken($_GET['csrf_token'])) {
        $error = 'Ошибка безопасности. Действие отклонено.';
        $action = '';
    }
}

// Получаем текущего пользователя
$current_user_id = $_SESSION['admin_id'];
$current_user_role = $_SESSION['admin_role'];

// Обработка создания пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    if (!isset($_POST['csrf_token']) || !validateAdminCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Действие отклонено.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'manager';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Валидация
        $errors = [];
        
        if (empty($username)) {
            $errors[] = 'Имя пользователя обязательно';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Имя пользователя должно быть не менее 3 символов';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Имя пользователя может содержать только латинские буквы, цифры и подчеркивания';
        }
        
        if (empty($password)) {
            $errors[] = 'Пароль обязателен';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Пароль должен быть не менее 8 символов';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Пароли не совпадают';
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        }
        
        if (!in_array($role, ['admin', 'manager'])) {
            $errors[] = 'Некорректная роль';
        }
        
        // Проверяем уникальность username
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("SELECT id FROM admins WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $errors[] = 'Пользователь с таким именем уже существует';
                }
            } catch (Exception $e) {
                $errors[] = 'Ошибка проверки уникальности пользователя';
            }
        }
        
        if (empty($errors)) {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO admins 
                    (username, password_hash, email, full_name, role, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $username,
                    $password_hash,
                    $email,
                    $full_name,
                    $role,
                    $is_active
                ]);
                
                $new_user_id = $db->lastInsertId();
                
                // Логируем действие
                logAdminAction('user_created', 'admin', $new_user_id, [
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'is_active' => $is_active
                ]);
                
                $message = 'Пользователь успешно создан';
                header('Location: users.php?message=' . urlencode($message));
                exit;
            } catch (Exception $e) {
                $error = 'Ошибка при создании пользователя: ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Обработка редактирования пользователя
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    if (!isset($_POST['csrf_token']) || !validateAdminCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Действие отклонено.';
    } else {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'manager';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $change_password = isset($_POST['change_password']) && $_POST['change_password'] == '1';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Проверяем, что пользователь существует
        $stmt = $db->prepare("SELECT id, username, role FROM admins WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Пользователь не найден';
        } else {
            $errors = [];
            
            // Нельзя менять роль суперадмина (если только у вас не есть логика суперадмина)
            if ($current_user_role != 'admin' && $user['role'] == 'admin') {
                $error = 'Недостаточно прав для редактирования администратора';
            } else {
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Некорректный email';
                }
                
                if (!in_array($role, ['admin', 'manager'])) {
                    $errors[] = 'Некорректная роль';
                }
                
                // Нельзя изменить свою роль на manager если вы последний админ
                if ($user_id == $current_user_id && $role != 'admin') {
                    $stmt = $db->prepare("SELECT COUNT(*) as admin_count FROM admins WHERE role = 'admin' AND id != ? AND is_active = 1");
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch();
                    if ($result['admin_count'] == 0) {
                        $errors[] = 'Нельзя изменить свою роль, вы единственный активный администратор';
                    }
                }
                
                if ($change_password) {
                    if (strlen($new_password) < 8) {
                        $errors[] = 'Новый пароль должен быть не менее 8 символов';
                    } elseif ($new_password !== $confirm_password) {
                        $errors[] = 'Пароли не совпадают';
                    }
                }
                
                if (empty($errors)) {
                    try {
                        if ($change_password) {
                            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("
                                UPDATE admins 
                                SET email = ?, full_name = ?, role = ?, is_active = ?, password_hash = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$email, $full_name, $role, $is_active, $password_hash, $user_id]);
                        } else {
                            $stmt = $db->prepare("
                                UPDATE admins 
                                SET email = ?, full_name = ?, role = ?, is_active = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$email, $full_name, $role, $is_active, $user_id]);
                        }
                        
                        // Логируем действие
                        $changes = [
                            'email' => $email,
                            'full_name' => $full_name,
                            'role' => $role,
                            'is_active' => $is_active,
                            'password_changed' => $change_password
                        ];
                        logAdminAction('user_updated', 'admin', $user_id, $changes);
                        
                        $message = 'Данные пользователя обновлены';
                        header('Location: users.php?message=' . urlencode($message));
                        exit;
                    } catch (Exception $e) {
                        $error = 'Ошибка при обновлении пользователя: ' . $e->getMessage();
                    }
                } else {
                    $error = implode('<br>', $errors);
                }
            }
        }
    }
}

// Обработка удаления пользователя
if ($action == 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Нельзя удалить себя
    if ($user_id == $current_user_id) {
        $error = 'Нельзя удалить свой собственный аккаунт';
    } else {
        // Проверяем, что пользователь существует
        $stmt = $db->prepare("SELECT id, username, role FROM admins WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Пользователь не найден';
        } elseif ($current_user_role != 'admin' && $user['role'] == 'admin') {
            $error = 'Недостаточно прав для удаления администратора';
        } else {
            try {
                // Проверяем, что после удаления останется хотя бы один активный админ
                if ($user['role'] == 'admin') {
                    $stmt = $db->prepare("SELECT COUNT(*) as admin_count FROM admins WHERE role = 'admin' AND id != ? AND is_active = 1");
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch();
                    if ($result['admin_count'] == 0) {
                        $error = 'Нельзя удалить последнего активного администратора';
                    } else {
                        $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
                        $stmt->execute([$user_id]);
                        
                        logAdminAction('user_deleted', 'admin', $user_id, ['username' => $user['username']]);
                        
                        $message = 'Пользователь удален';
                        header('Location: users.php?message=' . urlencode($message));
                        exit;
                    }
                } else {
                    $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    logAdminAction('user_deleted', 'admin', $user_id, ['username' => $user['username']]);
                    
                    $message = 'Пользователь удален';
                    header('Location: users.php?message=' . urlencode($message));
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Ошибка при удалении пользователя: ' . $e->getMessage();
            }
        }
    }
}

// Обработка включения/выключения пользователя
if ($action == 'toggle_status' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Нельзя отключить себя
    if ($user_id == $current_user_id) {
        $error = 'Нельзя отключить свой собственный аккаунт';
    } else {
        $stmt = $db->prepare("SELECT id, username, role, is_active FROM admins WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Пользователь не найден';
        } elseif ($current_user_role != 'admin' && $user['role'] == 'admin') {
            $error = 'Недостаточно прав для изменения статуса администратора';
        } else {
            $new_status = $user['is_active'] ? 0 : 1;
            
            // Проверяем, что не отключаем последнего активного админа
            if ($user['role'] == 'admin' && $new_status == 0) {
                $stmt = $db->prepare("SELECT COUNT(*) as admin_count FROM admins WHERE role = 'admin' AND id != ? AND is_active = 1");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch();
                if ($result['admin_count'] == 0) {
                    $error = 'Нельзя отключить последнего активного администратора';
                }
            }
            
            if (empty($error)) {
                try {
                    $stmt = $db->prepare("UPDATE admins SET is_active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    
                    logAdminAction('user_status_changed', 'admin', $user_id, [
                        'old_status' => $user['is_active'],
                        'new_status' => $new_status
                    ]);
                    
                    $message = $new_status ? 'Пользователь активирован' : 'Пользователь деактивирован';
                    header('Location: users.php?message=' . urlencode($message));
                    exit;
                } catch (Exception $e) {
                    $error = 'Ошибка при изменении статуса: ' . $e->getMessage();
                }
            }
        }
    }
}

// Получаем список пользователей
try {
    $stmt = $db->prepare("
        SELECT id, username, email, full_name, role, is_active, 
               last_login, created_at, failed_attempts, locked_until
        FROM admins 
        ORDER BY 
            CASE role 
                WHEN 'admin' THEN 1 
                WHEN 'manager' THEN 2 
                ELSE 3 
            END,
            created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Ошибка при получении списка пользователей: ' . $e->getMessage();
    $users = [];
}

// Получаем информацию об администраторе для шапки
$adminName = $_SESSION['admin_name'] ?? 'Администратор';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - ZLOCK</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        <?php include 'normalize.css'; ?>
        /* Стили для уведомления о пароле */
        .password-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .password-notification .close-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .password-notification .close-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .password-display {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            word-break: break-all;
            letter-spacing: 1px;
        }
                
        /* Дополнительные стили для страницы пользователей */
        .users-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .create-user-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .create-user-btn:hover {
            background: #218838;
            color: white;
            text-decoration: none;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .users-table tr:hover {
            background: #f8f9fa;
        }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .role-manager {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-locked {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .user-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f7ff;
        }
        
        .modal-title {
            margin: 0;
            color: #041c2c;
            font-size: 22px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close-modal:hover {
            background: #f8f9fa;
            color: #041c2c;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ced4da;
            margin-bottom: 20px;
        }
        
        .last-login {
            font-size: 12px;
            color: #6c757d;
        }
        
        .current-user {
            background: #f0f7ff !important;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .users-table {
                font-size: 14px;
            }
            
            .users-table th,
            .users-table td {
                padding: 8px 4px;
            }
            
            .user-actions {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 20px;
                margin: 10% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>

    <div class='header-container'>
        <!-- Верхняя часть с лого и пользователем -->
        <div class='header-top'>
            <div class='brand-section'>
                <a href="/admin/" class="sidebar-logo" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: white;">
                    <i class="fas fa-lock" style="font-size: 24px; color: #3b82f6;"></i>
                    <span style="font-size: 18px; font-weight: 700;">ZIP-Admin</span>
                </a>
            </div>
            
            <div class='user-section'>
                <div class='user-info'>
                    <strong><?php echo safeOutput($adminName); ?></strong>
                    <div class='user-role'><?php echo safeOutput($adminRole); ?></div>
                </div>
                
                <a href='/admin/logout.php' class='logout-btn'>
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Выйти</span>
                </a>
            </div>
        </div>
        
        <!-- Меню -->
        <div class='menu'>
            <a href='/admin/' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-tachometer-alt"></i></div>
                <div class='menu-text'>
                    <strong>Панель управления</strong>
                    <p>Главная страница админ-панели</p>
                </div>
            </a>
            
            <a href='/admin/leads.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-list"></i></div>
                <div class='menu-text'>
                    <strong>Все заявки</strong>
                    <p>Просмотр всех заявок системы</p>
                </div>
            </a>
            
            <a href='/admin/calculations.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-calculator"></i></div>
                <div class='menu-text'>
                    <strong>Расчёты</strong>
                    <p>Финансовые расчеты и отчеты</p>
                </div>
            </a>
            
            <a href='/admin/settings.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-cog"></i></div>
                <div class='menu-text'>
                    <strong>Настройки</strong>
                    <p>Настройки системы</p>
                </div>
            </a>
            
            <a href='/admin/users.php' class='menu-item active'>
                <div class='fa-icon'><i class="fas fa-users"></i></div>
                <div class='menu-text'>
                    <strong>Пользователи</strong>
                    <p>Управление пользователями</p>
                </div>
            </a>
        </div>
    </div>
    
    <div class='content-container'>
        <div class="users-container">
            <!-- Заголовок и кнопка создания -->
            <div class="users-header">
                <div>
                    <h1>Управление пользователями</h1>
                    <p>Создание и управление учетными записями администраторов</p>
                </div>
                <a href="#" class="create-user-btn" onclick="openCreateModal(); return false;">
                    <i class="fas fa-plus"></i>
                    Создать пользователя
                </a>
            </div>

            <!-- Сообщения -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo safeOutput($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo safeOutput($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-success">
                    <?php echo safeOutput($_GET['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Таблица пользователей -->
            <div class="requests-table-container">
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Пользователи не найдены</h3>
                        <p>Создайте первого пользователя системы</p>
                    </div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя пользователя</th>
                                <th>ФИО</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Дата создания</th>
                                <th>Последний вход</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="<?php echo $user['id'] == $current_user_id ? 'current-user' : ''; ?>">
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <strong><?php echo safeOutput($user['username']); ?></strong>
                                        <?php if ($user['id'] == $current_user_id): ?>
                                            <br><small style="color: #007bff;">(это вы)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo safeOutput($user['full_name'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (!empty($user['email'])): ?>
                                            <a href="mailto:<?php echo safeOutput($user['email']); ?>">
                                                <?php echo safeOutput($user['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">не указан</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php 
                                            $roleLabels = [
                                                'admin' => 'Администратор',
                                                'manager' => 'Менеджер'
                                            ];
                                            echo isset($roleLabels[$user['role']]) ? $roleLabels[$user['role']] : $user['role'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = 'active';
                                        $statusText = 'Активен';
                                        
                                        if (!$user['is_active']) {
                                            $status = 'inactive';
                                            $statusText = 'Неактивен';
                                        } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                                            $status = 'locked';
                                            $statusText = 'Заблокирован';
                                        } elseif ($user['failed_attempts'] >= 5) {
                                            $status = 'locked';
                                            $statusText = 'Заблокирован';
                                        }
                                        ?>
                                        <span class="status-badge status-<?php echo $status; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                                        <div class="last-login">
                                            <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['last_login'])): ?>
                                            <?php echo date('d.m.Y', strtotime($user['last_login'])); ?>
                                            <div class="last-login">
                                                <?php echo date('H:i', strtotime($user['last_login'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #6c757d;">никогда</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="user-actions">
                                            <button class="btn btn-sm btn-info edit-user-btn" 
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-fullname="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-role="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-active="<?php echo $user['is_active']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($user['id'] != $current_user_id): ?>
                                                <a href="?action=toggle_status&id=<?php echo $user['id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>" 
                                                   class="btn btn-sm <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                                   title="<?php echo $user['is_active'] ? 'Деактивировать' : 'Активировать'; ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </a>
                                                
                                                <a href="?action=delete&id=<?php echo $user['id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Удалить пользователя <?php echo addslashes($user['username']); ?>?')"
                                                   title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно создания пользователя -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Создание нового пользователя</h2>
                <button class="close-modal" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_username">Имя пользователя *</label>
                        <input type="text" id="create_username" name="username" class="form-control" required 
                               placeholder="Введите имя пользователя" minlength="3" maxlength="50">
                        <small style="color: #6c757d; font-size: 12px;">Только латинские буквы, цифры и подчеркивания</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_email">Email</label>
                        <input type="email" id="create_email" name="email" class="form-control" 
                               placeholder="email@example.com" maxlength="100">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_full_name">ФИО</label>
                        <input type="text" id="create_full_name" name="full_name" class="form-control" 
                               placeholder="Иванов Иван Иванович" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="create_role">Роль *</label>
                        <select id="create_role" name="role" class="form-control" required>
                            <option value="manager">Менеджер</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_password">Пароль *</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="password" id="create_password" name="password" class="form-control" required 
                                   placeholder="Нажмите 'Сгенерировать'" minlength="8" style="flex: 1;" readonly>
                            <button type="button" class="btn btn-info" onclick="generateAndSetPassword()" style="white-space: nowrap;">
                                <i class="fas fa-key"></i> Сгенерировать
                            </button>
                        </div>
                        <small style="color: #6c757d; font-size: 12px;">Пароль будет сгенерирован автоматически</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_confirm_password">Подтверждение пароля *</label>
                        <input type="password" id="create_confirm_password" name="confirm_password" class="form-control" required 
                               placeholder="Повторите пароль" readonly>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                        <strong style="color: #495057;">Информация о пароле</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px; color: #6c757d; font-size: 13px;">
                        <li>Пароль будет сгенерирован длиной 15 символов</li>
                        <li>Содержит буквы в верхнем и нижнем регистре, цифры и спецсимволы</li>
                        <li>После генерации будет показан в уведомлении</li>
                        <li>Обязательно сохраните пароль в безопасном месте!</li>
                    </ul>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="create_is_active" name="is_active" value="1" checked>
                    <label for="create_is_active">Активный пользователь</label>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать пользователя</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Модальное окно редактирования пользователя -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Редактирование пользователя</h2>
                <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id" value="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Имя пользователя</label>
                        <input type="text" id="edit_username" class="form-control" disabled style="background: #f8f9fa;">
                        <small style="color: #6c757d; font-size: 12px;">Имя пользователя нельзя изменить</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" class="form-control" 
                               placeholder="email@example.com" maxlength="100">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_full_name">ФИО</label>
                        <input type="text" id="edit_full_name" name="full_name" class="form-control" 
                               placeholder="Иванов Иван Иванович" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_role">Роль</label>
                        <select id="edit_role" name="role" class="form-control" required>
                            <option value="manager">Менеджер</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                </div>
                
                <div class="checkbox-group" style="margin-bottom: 20px;">
                    <input type="checkbox" id="edit_change_password" name="change_password" value="1">
                    <label for="edit_change_password">Изменить пароль</label>
                </div>
                
                <div id="passwordFields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_new_password">Новый пароль</label>
                            <input type="password" id="edit_new_password" name="new_password" 
                                   class="form-control" minlength="8" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_confirm_password">Подтверждение пароля</label>
                            <input type="password" id="edit_confirm_password" name="confirm_password" 
                                   class="form-control" disabled>
                        </div>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                    <label for="edit_is_active">Активный пользователь</label>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </div>
            </form>
        </div>
    </div>

<script>
// ========== ГЕНЕРАЦИЯ ПАРОЛЯ ==========
function generatePassword(length = 15) {
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?";
    let password = "";
    
    // Добавляем разные типы символов
    password += "abcdefghijklmnopqrstuvwxyz".charAt(Math.floor(Math.random() * 26));
    password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ".charAt(Math.floor(Math.random() * 26));
    password += "0123456789".charAt(Math.floor(Math.random() * 10));
    password += "!@#$%^&*()_+-=[]{}|;:,.<>?".charAt(Math.floor(Math.random() * 26));
    
    // Добиваем длину
    for (let i = password.length; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    
    // Перемешиваем
    return password.split('').sort(() => Math.random() - 0.5).join('');
}

function generateAndSetPassword() {
    const password = generatePassword(15);
    const passwordField = document.getElementById('create_password');
    const confirmField = document.getElementById('create_confirm_password');
    
    if (passwordField && confirmField) {
        passwordField.value = password;
        confirmField.value = password;
        
        // Показываем на 3 секунды
        passwordField.type = 'text';
        confirmField.type = 'text';
        
        setTimeout(() => {
            passwordField.type = 'password';
            confirmField.type = 'password';
        }, 3000);
        
        showPasswordNotification(password);
    }
}

function showPasswordNotification(password) {
    // Удаляем старое уведомление если есть
    const oldNotification = document.getElementById('passwordNotification');
    if (oldNotification) oldNotification.remove();
    
    // Создаем новое
    const notification = document.createElement('div');
    notification.id = 'passwordNotification';
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; max-width: 400px;';
    
    notification.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <strong style="font-size: 16px;">🎉 Сгенерирован пароль!</strong>
            <button onclick="document.getElementById('passwordNotification').remove()" style="background: none; border: none; color: white; cursor: pointer; font-size: 18px;">&times;</button>
        </div>
        <div>
            <p style="margin: 0 0 10px 0;">Скопируйте пароль:</p>
            <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 4px; margin-bottom: 10px; font-family: monospace; font-size: 16px; word-break: break-all;">
                ${password}
            </div>
            <button onclick="copyToClipboard('${password.replace(/'/g, "\\'")}')" style="background: #17a2b8; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; width: 100%;">
                <i class="fas fa-copy"></i> Копировать
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (document.getElementById('passwordNotification')) {
            document.getElementById('passwordNotification').remove();
        }
    }, 10000);
}

function copyToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    alert('Пароль скопирован в буфер обмена!');
    
    const notification = document.getElementById('passwordNotification');
    if (notification) notification.remove();
}

// ========== МОДАЛЬНЫЕ ОКНА ==========
function openCreateModal() {
    const modal = document.getElementById('createModal');
    if (modal) {
        modal.style.display = 'block';
        const usernameField = document.getElementById('create_username');
        if (usernameField) usernameField.focus();
    }
}

function openEditModal(userId, username, email, fullName, role, isActive) {
    const modal = document.getElementById('editModal');
    if (!modal) return;
    
    // Заполняем поля
    const userIdField = document.getElementById('edit_user_id');
    const usernameField = document.getElementById('edit_username');
    const emailField = document.getElementById('edit_email');
    const fullNameField = document.getElementById('edit_full_name');
    const roleField = document.getElementById('edit_role');
    const isActiveField = document.getElementById('edit_is_active');
    
    if (userIdField) userIdField.value = userId;
    if (usernameField) usernameField.value = username || '';
    if (emailField) emailField.value = email || '';
    if (fullNameField) fullNameField.value = fullName || '';
    if (roleField) roleField.value = role || 'manager';
    if (isActiveField) isActiveField.checked = isActive == 1;
    
    // Сбрасываем поля пароля
    const changePasswordCheckbox = document.getElementById('edit_change_password');
    if (changePasswordCheckbox) {
        changePasswordCheckbox.checked = false;
        togglePasswordFields(false);
    }
    
    // Показываем модальное окно
    modal.style.display = 'block';
}

function togglePasswordFields(show) {
    const passwordFields = document.getElementById('passwordFields');
    const newPassword = document.getElementById('edit_new_password');
    const confirmPassword = document.getElementById('edit_confirm_password');
    
    if (passwordFields) passwordFields.style.display = show ? 'block' : 'none';
    if (newPassword) {
        newPassword.disabled = !show;
        newPassword.required = show;
        if (!show) newPassword.value = '';
    }
    if (confirmPassword) {
        confirmPassword.disabled = !show;
        confirmPassword.required = show;
        if (!show) confirmPassword.value = '';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// ========== ИНИЦИАЛИЗАЦИЯ ==========
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    
    // Обработка кликов по кнопкам редактирования
    document.querySelectorAll('.edit-user-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            const email = this.getAttribute('data-email');
            const fullName = this.getAttribute('data-fullname');
            const role = this.getAttribute('data-role');
            const isActive = this.getAttribute('data-active');
            
            openEditModal(userId, username, email, fullName, role, isActive);
        });
    });
    
    // Обработчик чекбокса смены пароля
    const changePasswordCheckbox = document.getElementById('edit_change_password');
    if (changePasswordCheckbox) {
        changePasswordCheckbox.addEventListener('change', function() {
            togglePasswordFields(this.checked);
        });
    }
    
    // Проверка совпадения паролей
    const createPassword = document.getElementById('create_password');
    const createConfirm = document.getElementById('create_confirm_password');
    
    if (createPassword && createConfirm) {
        function validatePassword() {
            if (createPassword.value !== createConfirm.value) {
                createConfirm.style.borderColor = '#dc3545';
            } else {
                createConfirm.style.borderColor = '#ced4da';
            }
        }
        
        createPassword.addEventListener('input', validatePassword);
        createConfirm.addEventListener('input', validatePassword);
    }
    
    // Автозаполнение имени из email
    const createEmail = document.getElementById('create_email');
    const createUsername = document.getElementById('create_username');
    
    if (createEmail && createUsername) {
        createEmail.addEventListener('blur', function() {
            if (this.value && !createUsername.value) {
                let username = this.value.split('@')[0];
                username = username.replace(/[^a-zA-Z0-9_]/g, '_');
                if (!/^[a-zA-Z]/.test(username)) {
                    username = 'user_' + username;
                }
                createUsername.value = username.substring(0, 50);
            }
        });
    }
    
    // Закрытие модальных окон при клике вне
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Закрытие по кнопке ×
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) modal.style.display = 'none';
        });
    });
    
    console.log('Initialization complete');
});
</script>

</body>
</html>