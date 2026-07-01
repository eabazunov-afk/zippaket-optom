<?php
/**
 * Админка: заказы интернет-магазина.
 * Список заказов, смена статуса (с проверкой матрицы переходов),
 * подтверждение оплаты «по счёту» (pending_payment → paid).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/includes/security_config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

checkAdminAuth();

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../includes/order.php';

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Сессия истекла, обновите страницу.';
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'set_status') {
            $res = order_admin_set_status($orderId, (string)($_POST['status'] ?? ''));
            if ($res['ok']) {
                $notice = 'Статус заказа обновлён.';
            } else {
                $error = $res['error'] === 'forbidden_transition' ? 'Такой переход статуса запрещён.' : 'Не удалось сменить статус.';
            }
        } elseif ($action === 'confirm_invoice') {
            $res = order_admin_set_status($orderId, 'paid');
            if ($res['ok']) {
                $db = getDbConnection();
                $db->prepare("UPDATE orders SET payment_status = 'manual_invoice' WHERE id = ?")->execute([$orderId]);
                $notice = 'Оплата по счёту подтверждена, заказ переведён в «Оплачен».';
            } else {
                $error = 'Не удалось подтвердить оплату (статус заказа не позволяет переход).';
            }
        }
    }
}

$csrf = generateCsrfToken();
$orders = orders_list(300);

$statusLabels = [
    'new' => 'Новый', 'pending_payment' => 'Ожидает оплаты', 'paid' => 'Оплачен',
    'processing' => 'В работе', 'shipped' => 'Отгружен', 'done' => 'Выполнен', 'canceled' => 'Отменён',
];
// Пастельные пары [фон, текст] под светлую тему админки.
function status_badge_colors(string $s): array {
    return [
        'new'             => ['#dbeafe', '#1d4ed8'],
        'pending_payment' => ['#fef3c7', '#d97706'],
        'paid'            => ['#d1fae5', '#047857'],
        'processing'      => ['#e0e7ff', '#4338ca'],
        'shipped'         => ['#ede9fe', '#6d28d9'],
        'done'            => ['#dcfce7', '#15803d'],
        'canceled'        => ['#fee2e2', '#dc2626'],
    ][$s] ?? ['#e9ecef', '#495057'];
}

$adminName = $_SESSION['admin_name'] ?? 'Администратор';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказы магазина — ZIP-Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        <?php include __DIR__ . '/normalize.css'; ?>

        /* Заказы — узкие доп-стили поверх normalize */
        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .msg.ok { background: #d1fae5; color: #047857; }
        .msg.err { background: #fee2e2; color: #dc2626; }
        .requests-table td .muted { color: #6c757d; font-size: 12px; }
        .requests-table td .pm { color: #6c757d; font-size: 11px; }
        form.inline { display: inline-flex; gap: 6px; align-items: center; margin: 0; }
        form.inline select { padding: 6px 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 13px; }
        .btn-gold { background: #FFB020; color: #1a1300; font-weight: 700; }
        .btn-gold:hover { background: #e89e12; color: #1a1300; }
    </style>
</head>
<body>

    <div class='header-container'>
        <div class='header-top'>
            <div class='brand-section'>
                <a href="/admin/" class="sidebar-logo" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:white;">
                    <i class="fas fa-lock" style="font-size:24px; color:#3b82f6;"></i>
                    <span style="font-size:18px; font-weight:700;">ZIP-Admin</span>
                </a>
            </div>
            <div class='user-section'>
                <div class='user-info'>
                    <strong><?php echo htmlspecialchars($adminName); ?></strong>
                    <div class='user-role'><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <a href='/admin/logout.php' class='logout-btn'>
                    <i class="fas fa-sign-out-alt"></i><span>Выйти</span>
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
            <a href='/admin/orders.php' class='menu-item active'>
                <div class='fa-icon'><i class="fas fa-shopping-bag"></i></div>
                <div class='menu-text'><strong>Заказы магазина</strong><p>Заказы и оплаты из каталога</p></div>
            </a>
            <a href='/admin/calculations.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-calculator"></i></div>
                <div class='menu-text'><strong>Расчёты</strong><p>Финансовые расчеты и отчеты</p></div>
            </a>
            <a href='/admin/settings.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-cog"></i></div>
                <div class='menu-text'><strong>Настройки</strong><p>Настройки системы</p></div>
            </a>
            <a href='/admin/statistics.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-chart-pie"></i></div>
                <div class='menu-text'><strong>Статистика</strong><p>Анализ трафика и конверсий</p></div>
            </a>
        </div>
    </div>

    <div class='content-container'>
        <div class='content-header'>
            <h1>Заказы магазина (<?= count($orders) ?>)</h1>
            <p>Управление заказами из каталога, статусы и подтверждение оплаты по счёту</p>
        </div>

        <?php if ($notice): ?><div class="msg ok"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="requests-table-container">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Заказов пока нет</h3>
                <p>Здесь появятся заказы, оформленные через каталог</p>
            </div>
        <?php else: ?>
        <table class="requests-table">
            <thead>
                <tr>
                    <th>№ / дата</th><th>Клиент</th><th>Контакт</th><th>Оплата</th><th>Сумма</th><th>Статус</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o):
                    $st = $o['status'];
                    $isInvoicePending = ($o['payment_method'] === 'invoice' && $st === 'pending_payment');
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($o['order_number']) ?></strong><br>
                        <span class="muted"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($o['created_at'] ?? 'now'))) ?></span>
                    </td>
                    <td>
                        <?= htmlspecialchars($o['customer_name']) ?>
                        <?php if ($o['customer_type'] === 'company'): ?>
                            <br><span class="muted"><?= htmlspecialchars($o['company_name']) ?><?= $o['inn'] ? ', ИНН ' . htmlspecialchars($o['inn']) : '' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($o['phone']) ?><?php if ($o['email']): ?><br><span class="muted"><?= htmlspecialchars($o['email']) ?></span><?php endif; ?></td>
                    <td>
                        <?= $o['payment_method'] === 'online' ? 'Картой' : 'По счёту' ?>
                        <?php if ($o['payment_method'] === 'invoice'): ?>
                            <br><a href="/invoice.php?order=<?= urlencode($o['order_number']) ?>&t=<?= urlencode($o['access_token'] ?? '') ?>" target="_blank">счёт</a>
                        <?php endif; ?>
                        <?php if (!empty($o['payment_status'])): ?><br><span class="pm"><?= htmlspecialchars($o['payment_status']) ?></span><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap"><?= number_format((float)$o['total'], 2, ',', ' ') ?> ₽</td>
                    <td><?php [$bbg, $bfg] = status_badge_colors($st); ?><span class="status-badge" style="background:<?= $bbg ?>; color:<?= $bfg ?>"><?= htmlspecialchars($statusLabels[$st] ?? $st) ?></span></td>
                    <td>
                        <form class="inline" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                            <input type="hidden" name="action" value="set_status">
                            <select name="status">
                                <?php foreach ($statusLabels as $sv => $sl): ?>
                                    <option value="<?= $sv ?>" <?= $sv === $st ? 'selected' : '' ?>><?= htmlspecialchars($sl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-primary" type="submit">OK</button>
                        </form>
                        <?php if ($isInvoicePending): ?>
                        <form class="inline" method="POST" style="margin-top:6px">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                            <input type="hidden" name="action" value="confirm_invoice">
                            <button class="btn btn-sm btn-gold" type="submit"><i class="fas fa-check"></i> Оплата по счёту</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div><!-- /requests-table-container -->
    </div><!-- /content-container -->
</body>
</html>
