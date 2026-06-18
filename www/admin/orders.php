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
function status_color(string $s): string {
    return ['paid' => '#10b981', 'pending_payment' => '#f59e0b', 'canceled' => '#ef4444',
            'processing' => '#3b82f6', 'shipped' => '#6366f1', 'done' => '#059669', 'new' => '#64748b'][$s] ?? '#64748b';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказы — ZLOCK admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; }
        .top { background: #111827; padding: 16px 24px; display: flex; align-items: center; gap: 20px; border-bottom: 1px solid #1f2937; }
        .top a { color: #94a3b8; text-decoration: none; font-size: 14px; }
        .top a:hover, .top a.active { color: #5FE3D0; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 22px; margin: 0 0 18px; }
        .msg { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        .msg.ok { background: rgba(16,185,129,.15); color: #6ee7b7; }
        .msg.err { background: rgba(239,68,68,.15); color: #fca5a5; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; background: #1e293b; border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #273449; vertical-align: middle; }
        th { background: #172033; color: #94a3b8; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #06121f; }
        .pm { font-size: 11px; color: #94a3b8; }
        select, .btn { font-family: inherit; font-size: 12px; border-radius: 8px; padding: 6px 10px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; cursor: pointer; }
        .btn-primary { background: #0A8F8F; border-color: #0A8F8F; color: #fff; }
        .btn-gold { background: #FFB020; border-color: #FFB020; color: #1a1300; font-weight: 700; }
        form.inline { display: inline-flex; gap: 6px; align-items: center; margin: 0; }
        .muted { color: #64748b; font-size: 11px; }
        .empty { padding: 40px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    <div class="top">
        <b style="color:#fff">ZLOCK admin</b>
        <a href="/admin/">Дашборд</a>
        <a href="/admin/leads.php">Заявки</a>
        <a href="/admin/orders.php" class="active">Заказы</a>
        <a href="/admin/logout.php" style="margin-left:auto">Выход</a>
    </div>
    <div class="wrap">
        <h1>Заказы магазина <span class="muted">(<?= count($orders) ?>)</span></h1>
        <?php if ($notice): ?><div class="msg ok"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="empty">Заказов пока нет.</div>
        <?php else: ?>
        <table>
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
                        <b><?= htmlspecialchars($o['order_number']) ?></b><br>
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
                            <br><a class="muted" href="/invoice.php?order=<?= urlencode($o['order_number']) ?>" target="_blank">счёт</a>
                        <?php endif; ?>
                        <?php if (!empty($o['payment_status'])): ?><br><span class="pm"><?= htmlspecialchars($o['payment_status']) ?></span><?php endif; ?>
                    </td>
                    <td style="white-space:nowrap"><?= number_format((float)$o['total'], 2, ',', ' ') ?> ₽</td>
                    <td><span class="badge" style="background:<?= status_color($st) ?>"><?= htmlspecialchars($statusLabels[$st] ?? $st) ?></span></td>
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
                            <button class="btn btn-primary" type="submit">OK</button>
                        </form>
                        <?php if ($isInvoicePending): ?>
                        <form class="inline" method="POST" style="margin-top:6px">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                            <input type="hidden" name="action" value="confirm_invoice">
                            <button class="btn btn-gold" type="submit"><i class="fas fa-check"></i> Оплата по счёту</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>
