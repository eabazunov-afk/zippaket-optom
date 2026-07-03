<?php
require_once '../includes/init.php';
require_once '../includes/app_settings.php';
require_once 'includes/security_config.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

// Авторизация + доступ к странице (view_settings: superadmin, admin)
checkAdminAuth();
$current_page = basename($_SERVER['PHP_SELF']);
checkPageAccess($current_page);

$message = '';
$error   = '';

// Текущие значения из БД (с фолбэком на дефолты, если миграция не накатана)
$eva = get_setting('calc_price_eva', '380');
$pvd = get_setting('calc_price_pvd', '360');

$canEdit = checkPermission('edit_settings'); // сохранять может только superadmin

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('edit_settings'); // 403, если прав нет

    if (!isset($_POST['csrf_token']) || !validateAdminCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Действие отклонено.';
    } else {
        $in_eva = trim($_POST['calc_price_eva'] ?? '');
        $in_pvd = trim($_POST['calc_price_pvd'] ?? '');

        // Разрешаем запятую как десятичный разделитель
        $ne = str_replace(',', '.', $in_eva);
        $np = str_replace(',', '.', $in_pvd);

        $errors = [];
        if (!is_numeric($ne) || (float)$ne <= 0 || (float)$ne > 100000) {
            $errors[] = 'Цена EVA должна быть числом больше 0 и не более 100000';
        }
        if (!is_numeric($np) || (float)$np <= 0 || (float)$np > 100000) {
            $errors[] = 'Цена ПВД должна быть числом больше 0 и не более 100000';
        }

        if ($errors) {
            $error = implode('. ', $errors);
            $eva = $in_eva; // показываем введённое, чтобы не терять правку
            $pvd = $in_pvd;
        } else {
            $ok = set_setting('calc_price_eva', (string)(float)$ne)
               && set_setting('calc_price_pvd', (string)(float)$np);
            if ($ok) {
                $message = 'Настройки сохранены';
                $eva = (float)$ne;
                $pvd = (float)$np;
            } else {
                $error = 'Не удалось сохранить настройки. Проверьте, что таблица settings создана (миграция 2026-07-03-settings.sql).';
            }
        }
    }
}

$adminName = $_SESSION['admin_name'] ?? 'Администратор';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - ZLOCK</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        <?php include 'normalize.css'; ?>

        .settings-container { max-width: 720px; margin: 0 auto; }
        .settings-header { margin-bottom: 24px; }
        .settings-header h1 { margin: 0 0 6px; }
        .settings-header p { margin: 0; color: #6b7280; }

        .settings-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .settings-card h2 {
            font-size: 18px;
            margin: 0 0 4px;
            display: flex; align-items: center; gap: 8px;
        }
        .settings-card .hint { color: #6b7280; font-size: 13px; margin: 0 0 20px; }

        .form-field { margin-bottom: 18px; }
        .form-field label { display: block; font-weight: 600; margin-bottom: 6px; }
        .input-suffix { position: relative; display: inline-flex; align-items: center; width: 100%; }
        .input-suffix input {
            width: 100%;
            padding: 10px 60px 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
        }
        .input-suffix input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .input-suffix input:disabled { background: #f3f4f6; color: #6b7280; }
        .input-suffix .suffix { position: absolute; right: 12px; color: #9ca3af; font-size: 14px; }

        .btn-save {
            background: #3b82f6; color: #fff; border: none;
            padding: 11px 22px; border-radius: 8px; font-size: 15px;
            font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-save:hover { background: #2563eb; }

        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .readonly-note { margin-top: 16px; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; padding: 10px 14px; border-radius: 8px; font-size: 13px; }
    </style>
</head>
<body>

    <div class='header-container'>
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

        <div class='menu'>
            <a href='/admin/' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-tachometer-alt"></i></div>
                <div class='menu-text'><strong>Панель управления</strong><p>Главная страница админ-панели</p></div>
            </a>
            <a href='/admin/leads.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-list"></i></div>
                <div class='menu-text'><strong>Все заявки</strong><p>Просмотр всех заявок системы</p></div>
            </a>
            <a href='/admin/calculations.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-calculator"></i></div>
                <div class='menu-text'><strong>Расчёты</strong><p>Финансовые расчеты и отчеты</p></div>
            </a>
            <a href='/admin/settings.php' class='menu-item active'>
                <div class='fa-icon'><i class="fas fa-cog"></i></div>
                <div class='menu-text'><strong>Настройки</strong><p>Настройки системы</p></div>
            </a>
            <a href='/admin/users.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-users"></i></div>
                <div class='menu-text'><strong>Пользователи</strong><p>Управление пользователями</p></div>
            </a>
        </div>
    </div>

    <div class='content-container'>
        <div class="settings-container">
            <div class="settings-header">
                <h1>Настройки</h1>
                <p>Параметры калькулятора стоимости</p>
            </div>

            <?php if ($message): ?>
                <div class="flash ok"><i class="fas fa-check-circle"></i> <?php echo safeOutput($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="flash err"><i class="fas fa-exclamation-circle"></i> <?php echo safeOutput($error); ?></div>
            <?php endif; ?>

            <div class="settings-card">
                <h2><i class="fas fa-coins"></i> Цены сырья калькулятора</h2>
                <p class="hint">Стоимость материала за килограмм. По этим ставкам калькулятор считает себестоимость пакета.</p>

                <form method="post" action="/admin/settings.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

                    <div class="form-field">
                        <label for="calc_price_eva">EVA (матовый)</label>
                        <div class="input-suffix">
                            <input type="text" inputmode="decimal" id="calc_price_eva" name="calc_price_eva"
                                   value="<?php echo htmlspecialchars((string)$eva); ?>"
                                   <?php echo $canEdit ? '' : 'disabled'; ?>>
                            <span class="suffix">₽/кг</span>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="calc_price_pvd">ПВД (прозрачный)</label>
                        <div class="input-suffix">
                            <input type="text" inputmode="decimal" id="calc_price_pvd" name="calc_price_pvd"
                                   value="<?php echo htmlspecialchars((string)$pvd); ?>"
                                   <?php echo $canEdit ? '' : 'disabled'; ?>>
                            <span class="suffix">₽/кг</span>
                        </div>
                    </div>

                    <?php if ($canEdit): ?>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Сохранить</button>
                    <?php else: ?>
                        <div class="readonly-note">
                            <i class="fas fa-info-circle"></i>
                            Просмотр доступен, но изменять цены может только суперадминистратор.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
