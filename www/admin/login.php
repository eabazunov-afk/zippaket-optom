<?php
require_once '../includes/init.php';
require_once 'includes/auth.php';

// Если уже авторизован - редирект
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit;
}

$error = '';
$username = '';

// Инициализируем CSRF токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF токена
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Пожалуйста, обновите страницу.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
        
        if (empty($username) || empty($password)) {
            $error = 'Заполните все поля';
        } else {
            // Упрощенная проверка брутфорса
            $ip = $_SERVER['REMOTE_ADDR'];
            
            try {
                $db = getDbConnection();
                
                // Проверяем количество неудачных попыток
                $stmt = $db->prepare("
                    SELECT COUNT(*) as attempts 
                    FROM login_attempts 
                    WHERE (username = ? OR ip_address = ?) 
                    AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    AND success = 0
                ");
                $stmt->execute([$username, $ip]);
                $result = $stmt->fetch();
                
                if ($result && $result['attempts'] >= 5) {
                    $error = 'Слишком много неудачных попыток. Попробуйте через 15 минут.';
                    logAttempt($username, 'brute_force_blocked', false);
                } else {
                    // Проверяем администратора
                    $stmt = $db->prepare("
                        SELECT id, username, password_hash, full_name, role, email, is_active, 
                               failed_attempts, locked_until 
                        FROM admins 
                        WHERE username = ? 
                        LIMIT 1
                    ");
                    $stmt->execute([$username]);
                    $admin = $stmt->fetch();
                    
                    if (!$admin) {
                        $error = 'Неверное имя пользователя или пароль';
                        logAttempt($username, 'user_not_found', false);
                    } 
                    elseif (!$admin['is_active']) {
                        $error = 'Аккаунт отключен';
                        logAttempt($username, 'account_disabled', false);
                    }
                    elseif ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
                        $error = 'Аккаунт временно заблокирован';
                        logAttempt($username, 'account_locked', false);
                    }
                    elseif (!password_verify($password, $admin['password_hash'])) {
                        $error = 'Неверное имя пользователя или пароль';
                        
                        // Увеличиваем счетчик неудачных попыток
                        $stmt = $db->prepare("
                            UPDATE admins 
                            SET failed_attempts = failed_attempts + 1,
                                last_failed_attempt = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$admin['id']]);
                        
                        // Блокируем после 5 неудачных попыток
                        if ($admin['failed_attempts'] + 1 >= 5) {
                            $lock_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                            $stmt = $db->prepare("
                                UPDATE admins 
                                SET locked_until = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$lock_time, $admin['id']]);
                            $error = 'Слишком много неудачных попыток. Аккаунт заблокирован на 15 минут.';
                        }
                        
                        logAttempt($username, 'wrong_password', false);
                    } 
                    else {
                        // Успешная авторизация
                        // Сбрасываем счетчик неудачных попыток
                        $stmt = $db->prepare("
                            UPDATE admins 
                            SET failed_attempts = 0,
                                locked_until = NULL,
                                last_login = NOW(),
                                last_login_ip = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$ip, $admin['id']]);
                        
                        // Устанавливаем сессию
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_name'] = $admin['full_name'] ?: $admin['username'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['admin_ip'] = $ip;
                        $_SESSION['admin_last_activity'] = time();
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Обновляем токен
                        
                        // Если выбрано "запомнить меня"
                        if ($remember) {
                            $remember_token = bin2hex(random_bytes(32));
                            $expiry = time() + (30 * 24 * 60 * 60); // 30 дней
                            
                            setcookie(
                                'admin_remember',
                                $admin['id'] . ':' . $remember_token,
                                $expiry,
                                '/admin/',
                                '',
                                true,  // secure
                                true   // httponly
                            );
                            
                            // Сохраняем токен в БД
                            $stmt = $db->prepare("
                                UPDATE admins 
                                SET remember_token = ?,
                                    remember_expiry = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                password_hash($remember_token, PASSWORD_DEFAULT),
                                date('Y-m-d H:i:s', $expiry),
                                $admin['id']
                            ]);
                        }
                        
                        logAttempt($username, 'success', true);
                        
                        // Редирект
                        header('Location: /admin/');
                        exit;
                    }
                }
            } catch (Exception $e) {
                error_log("Auth error: " . $e->getMessage());
                $error = 'Произошла ошибка. Пожалуйста, попробуйте позже.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель - ZIP-Завод</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Ваш существующий CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }
        
        .login-header {
            background: #2563eb;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-logo {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .error-alert {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-alert i {
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 14px;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-with-icon input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            background: #1d4ed8;
        }
        
        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        
        .login-footer a {
            color: #2563eb;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .remember-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
            cursor: pointer;
        }
        
        .remember-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-lock"></i>
            </div>
            <h1>ZIP-Admin</h1>
            <p>Панель управления</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="form-group">
                    <label for="username">Имя пользователя</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               placeholder="Введите имя пользователя" 
                               value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                               autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="Введите пароль"
                               autocomplete="current-password">
                    </div>
                </div>
                
                <div class="remember-checkbox">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">Запомнить меня</label>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Войти в панель управления
                </button>
            </form>
            
            <div class="login-footer">
                <p>Для доступа требуется авторизация</p>
                <p><a href="/">← Вернуться на сайт</a></p>
            </div>
        </div>
    </div>
</body>
</html>