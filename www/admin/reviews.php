<?php
/**
 * Админка: модерация отзывов.
 * Список всех отзывов, публикация (approve) / скрытие (reject) / удаление.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/includes/security_config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/audit.php';

checkAdminAuth();

// Доступ к странице по матрице прав (view_reviews)
checkPageAccess(basename($_SERVER['PHP_SELF']));

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../includes/reviews.php';

$notice = '';
$error = '';
$canModerate = checkPermission('moderate_reviews');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Публикация, скрытие и удаление — изменяющие действия
    requirePermission('moderate_reviews');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Сессия истекла, обновите страницу.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        switch ($_POST['action'] ?? '') {
            case 'approve':
                review_set_approved($id, true);
                logAdminAction('review_approved', 'review', $id);
                $notice = 'Отзыв опубликован';
                break;
            case 'reject':
                review_set_approved($id, false);
                logAdminAction('review_rejected', 'review', $id);
                $notice = 'Отзыв скрыт';
                break;
            case 'delete':
                review_delete($id);
                logAdminAction('review_deleted', 'review', $id);
                $notice = 'Отзыв удалён';
                break;
        }
    }
}

$csrf = generateCsrfToken();
$reviews = reviews_all();

$adminName = $_SESSION['admin_name'] ?? 'Администратор';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отзывы — ZIP-Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        <?php include __DIR__ . '/normalize.css'; ?>

        /* Отзывы — узкие доп-стили поверх normalize */
        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .msg.ok { background: #d1fae5; color: #047857; }
        .msg.err { background: #fee2e2; color: #dc2626; }
        .requests-table td .muted { color: #6c757d; font-size: 12px; }
        .review-stars { color: #f59e0b; font-size: 15px; letter-spacing: 1px; white-space: nowrap; }
        .review-body { max-width: 420px; color: #343a40; font-size: 13px; line-height: 1.45; }
        form.inline { display: inline-flex; margin: 0; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
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
            <a href='/admin/orders.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-shopping-bag"></i></div>
                <div class='menu-text'><strong>Заказы магазина</strong><p>Заказы и оплаты из каталога</p></div>
            </a>
            <a href='/admin/reviews.php' class='menu-item active'>
                <div class='fa-icon'><i class="fas fa-star"></i></div>
                <div class='menu-text'><strong>Отзывы</strong><p>Модерация отзывов клиентов</p></div>
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
            <h1>Отзывы (<?= count($reviews) ?>)</h1>
            <p>Модерация отзывов клиентов: публикация, скрытие и удаление</p>
        </div>

        <?php if ($notice): ?><div class="msg ok"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="requests-table-container">
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Отзывов пока нет</h3>
                <p>Здесь появятся отзывы, оставленные клиентами</p>
            </div>
        <?php else: ?>
        <table class="requests-table">
            <thead>
                <tr>
                    <th>ID</th><th>Дата</th><th>Автор</th><th>Рейтинг</th><th>Текст</th><th>Статус</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $r):
                    $approved = (int)($r['is_approved'] ?? 0) === 1;
                ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><span class="muted"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at'] ?? 'now'))) ?></span></td>
                    <td>
                        <strong><?= htmlspecialchars($r['author_name'] ?? '') ?></strong>
                        <?php if (!empty($r['author_role'])): ?><br><span class="muted"><?= htmlspecialchars($r['author_role']) ?></span><?php endif; ?>
                    </td>
                    <td><span class="review-stars"><?= htmlspecialchars(review_stars((int)($r['rating'] ?? 5))) ?></span></td>
                    <td><div class="review-body"><?= nl2br(htmlspecialchars($r['body'] ?? '')) ?></div></td>
                    <td>
                        <?php if ($approved): ?>
                            <span class="status-badge" style="background:#d1fae5; color:#047857">Опубликован</span>
                        <?php else: ?>
                            <span class="status-badge" style="background:#fef3c7; color:#d97706">На модерации</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if (!$canModerate): ?>
                            <span class="muted">только просмотр</span>
                            <?php else: ?>
                            <?php if (!$approved): ?>
                            <form class="inline" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-sm btn-success" type="submit"><i class="fas fa-check"></i> Опубликовать</button>
                            </form>
                            <?php else: ?>
                            <form class="inline" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-sm btn-warning" type="submit"><i class="fas fa-eye-slash"></i> Скрыть</button>
                            </form>
                            <?php endif; ?>
                            <form class="inline" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-sm btn-danger" type="submit" data-tooltip="Удалить отзыв" aria-label="Удалить отзыв" onclick="return confirm('Удалить этот отзыв?')"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
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
