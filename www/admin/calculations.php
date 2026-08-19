<?php
require_once '../includes/init.php';
require_once 'includes/security_config.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

// Авторизация + доступ (view_calculations)
checkAdminAuth();
$current_page = basename($_SERVER['PHP_SELF']);
checkPageAccess($current_page);

$db = getDbConnection();

// Данные расчётов + агрегаты
$calculations = [];
$stats = ['count' => 0, 'sum' => 0.0, 'avg_unit' => 0.0];
try {
    $calculations = $db->query(
        "SELECT id, calculation_id, type, width, height, thickness, material,
                quantity, unit_price, total_price, ip_address, created_at
         FROM calculations
         ORDER BY created_at DESC
         LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);

    $row = $db->query(
        "SELECT COUNT(*) AS c, COALESCE(SUM(total_price),0) AS s, COALESCE(AVG(unit_price),0) AS a
         FROM calculations"
    )->fetch(PDO::FETCH_ASSOC);
    $stats = ['count' => (int)$row['c'], 'sum' => (float)$row['s'], 'avg_unit' => (float)$row['a']];
} catch (Throwable $e) {
    $loadError = 'Не удалось загрузить расчёты: ' . $e->getMessage();
}

$typeMeta = [
    'slider'  => ['label' => 'Слайдер',  'icon' => 'fa-arrows-left-right-to-line'],
    'ziplock' => ['label' => 'Zip-lock', 'icon' => 'fa-grip-lines'],
];

function fmt_money($v): string {
    return number_format((float)$v, 2, ',', ' ');
}
function fmt_int($v): string {
    return number_format((int)$v, 0, ',', ' ');
}

$adminName = $_SESSION['admin_name'] ?? 'Администратор';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расчёты - ZLOCK</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        <?php include 'normalize.css'; ?>

        .calc-type { display: inline-flex; align-items: center; gap: 7px; font-weight: 500; }
        .calc-type i { color: var(--z-bronze); }
        .calc-price { font-weight: 600; color: var(--z-text); white-space: nowrap; }
        .calc-total { font-weight: 700; color: var(--z-bronze-2); white-space: nowrap; }
        .calc-id { font-family: 'Courier New', monospace; font-size: 12px; color: var(--z-text-3); }
        .table-wrap { overflow-x: auto; }
    </style>
</head>
<body>

    <div class='header-container'>
        <div class='header-top'>
            <div class='brand-section'>
                <a href="/admin/" class="sidebar-logo" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--z-on-ink);">
                    <i class="fas fa-lock" style="font-size: 24px; color: var(--z-on-ink-acc);"></i>
                    <span style="font-size: 18px; font-weight: 700;">ZIP-Admin</span>
                </a>
            </div>
            <div class='user-section'>
                <div class='user-info'>
                    <strong><?php echo safeOutput($adminName); ?></strong>
                    <div class='user-role'><?php echo safeOutput($adminRole); ?></div>
                </div>
                <a href='/admin/logout.php' class='logout-btn'>
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Выйти</span>
                </a>
            </div>
        </div>

        <div class='menu'>
            <a href='/admin/' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-gauge-high"></i></div>
                <div class='menu-text'><strong>Панель управления</strong><p>Главная страница админ-панели</p></div>
            </a>
            <a href='/admin/leads.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-list-ul"></i></div>
                <div class='menu-text'><strong>Все заявки</strong><p>Просмотр всех заявок системы</p></div>
            </a>
            <a href='/admin/calculations.php' class='menu-item active'>
                <div class='fa-icon'><i class="fas fa-calculator"></i></div>
                <div class='menu-text'><strong>Расчёты</strong><p>Сохранённые расчёты калькулятора</p></div>
            </a>
            <a href='/admin/settings.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-gear"></i></div>
                <div class='menu-text'><strong>Настройки</strong><p>Настройки системы</p></div>
            </a>
            <a href='/admin/users.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-users"></i></div>
                <div class='menu-text'><strong>Пользователи</strong><p>Управление пользователями</p></div>
            </a>
        </div>
    </div>

    <div class='content-container'>
        <div class="content-header">
            <h1>Расчёты калькулятора</h1>
            <p>Сохранённые пользователями расчёты стоимости</p>
        </div>

        <?php if (!empty($loadError)): ?>
            <div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?php echo safeOutput($loadError); ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card stat-new">
                <div class="stat-label"><i class="fas fa-calculator"></i> Всего расчётов</div>
                <div class="stat-value"><?php echo fmt_int($stats['count']); ?></div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-label"><i class="fas fa-ruble-sign"></i> Сумма расчётов</div>
                <div class="stat-value" style="font-size:26px"><?php echo fmt_money($stats['sum']); ?> ₽</div>
            </div>
            <div class="stat-card stat-processed">
                <div class="stat-label"><i class="fas fa-tag"></i> Средняя цена/шт</div>
                <div class="stat-value" style="font-size:26px"><?php echo fmt_money($stats['avg_unit']); ?> ₽</div>
            </div>
        </div>

        <?php if (empty($calculations)): ?>
            <div class="empty-state">
                <i class="fas fa-calculator"></i>
                <p>Пока нет сохранённых расчётов.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Тип</th>
                            <th>Размер</th>
                            <th>Толщина</th>
                            <th>Материал</th>
                            <th>Тираж</th>
                            <th>Цена/шт</th>
                            <th>Сумма</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calculations as $c): ?>
                            <?php $meta = $typeMeta[$c['type']] ?? ['label' => $c['type'], 'icon' => 'fa-box']; ?>
                            <tr>
                                <td>
                                    <?php echo date('d.m.Y H:i', strtotime($c['created_at'])); ?>
                                    <div class="calc-id"><?php echo safeOutput($c['calculation_id']); ?></div>
                                </td>
                                <td><span class="calc-type"><i class="fas <?php echo $meta['icon']; ?>"></i><?php echo safeOutput($meta['label']); ?></span></td>
                                <td><?php echo (int)$c['width']; ?>×<?php echo (int)$c['height']; ?> см</td>
                                <td><?php echo (int)$c['thickness']; ?> мкм</td>
                                <td><?php echo safeOutput($c['material']); ?></td>
                                <td><?php echo fmt_int($c['quantity']); ?> шт</td>
                                <td class="calc-price"><?php echo fmt_money($c['unit_price']); ?> ₽</td>
                                <td class="calc-total"><?php echo fmt_money($c['total_price']); ?> ₽</td>
                                <td><span class="calc-id"><?php echo safeOutput($c['ip_address']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
