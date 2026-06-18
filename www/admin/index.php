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

// Простая проверка авторизации без вызова функции
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

// Получаем соединение с БД
$db = getDbConnection();

// Обработка действий
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';

// Изменение статуса заявки
if ($action === 'change_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    $validStatuses = array('new', 'processed', 'completed', 'rejected');
    
    if (in_array($status, $validStatuses)) {
        try {
            $stmt = $db->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute(array($status, $id));
            
            logAction('lead_status_changed', array(
                'lead_id' => $id,
                'new_status' => $status,
                'admin_id' => $_SESSION['admin_id']
            ));
            
            $message = '<div class="alert alert-success">Статус заявки обновлен</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Ошибка при обновлении статуса</div>';
        }
    }
}

// Удаление заявки
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute(array($id));
        
        logAction('lead_deleted', array(
            'lead_id' => $id,
            'admin_id' => $_SESSION['admin_id']
        ));
        
        $message = '<div class="alert alert-success">Заявка удалена</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Ошибка при удалении заявки</div>';
    }
}

// Получаем параметры фильтрации
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filterSearch = isset($_GET['search']) ? $_GET['search'] : '';

// Строим запрос с фильтрами
$query = "SELECT * FROM leads WHERE 1=1";
$params = array();

if ($filterStatus) {
    $query .= " AND status = ?";
    $params[] = $filterStatus;
}

if ($filterDateFrom) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $filterDateFrom;
}

if ($filterDateTo) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $filterDateTo;
}

if ($filterSearch) {
    $query .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchTerm = '%' . $filterSearch . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY created_at DESC";

// Получаем заявки
$stmt = $db->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Статистика за последние 30 дней
$statsStmt = $db->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM leads 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY status
    ORDER BY status
");
$stats = $statsStmt->fetchAll();

// Общая статистика
$totalStats = array(
    'total' => 0,
    'new' => 0,
    'processed' => 0,
    'completed' => 0,
    'rejected' => 0
);

foreach ($stats as $stat) {
    $status = $stat['status'];
    $count = $stat['count'];
    
    $totalStats['total'] += $count;
    
    if ($status == 'new') {
        $totalStats['new'] = $count;
    } elseif ($status == 'processed') {
        $totalStats['processed'] = $count;
    } elseif ($status == 'completed') {
        $totalStats['completed'] = $count;
    } elseif ($status == 'rejected') {
        $totalStats['rejected'] = $count;
    }
}

// Для отображения на главной также получим общее количество заявок за последние 30 дней
$total30daysStmt = $db->query("
    SELECT COUNT(*) as total_30_days 
    FROM leads 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$total30days = $total30daysStmt->fetch();
$totalStats['total'] = $total30days['total_30_days'] ?? 0;

// Получаем информацию об администраторе
$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Администратор';
$adminRole = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - ZLOCK</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        <?php include 'normalize.css'; ?>
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
            <a href='/admin/' class='menu-item active'>
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
            
            <a href='/admin/users.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-users"></i></div>
                <div class='menu-text'>
                    <strong>Пользователи</strong>
                    <p>Управление пользователями</p>
                </div>
            </a>
        </div>
    </div>
    
    <div class='content-container'>
        <div class='content-header'>
            <h1>Панель управления</h1>
            <p>Обзор системы и управление заявками</p>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card stat-new">
                <div class="stat-label">
                    <i class="fas fa-clock"></i>
                    Новые заявки
                </div>
                <div class="stat-value"><?php echo $totalStats['new']; ?></div>
            </div>
            
            <div class="stat-card stat-processed">
                <div class="stat-label">
                    <i class="fas fa-spinner"></i>
                    В обработке
                </div>
                <div class="stat-value"><?php echo $totalStats['processed']; ?></div>
            </div>
            
            <div class="stat-card stat-completed">
                <div class="stat-label">
                    <i class="fas fa-check-circle"></i>
                    Завершённые
                </div>
                <div class="stat-value"><?php echo $totalStats['completed']; ?></div>
            </div>
            
            <div class="stat-card stat-rejected">
                <div class="stat-label">
                    <i class="fas fa-times-circle"></i>
                    Отклонённые
                </div>
                <div class="stat-value"><?php echo $totalStats['rejected']; ?></div>
            </div>
        </div>
        
        <!-- Фильтры -->
        <div class="filters">
            <h3>Фильтры заявок</h3>
            <form method="GET" class="filter-form">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="form-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Все статусы</option>
                        <option value="new" <?php echo $filterStatus === 'new' ? 'selected' : ''; ?>>Новые</option>
                        <option value="processed" <?php echo $filterStatus === 'processed' ? 'selected' : ''; ?>>В обработке</option>
                        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Завершённые</option>
                        <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>Отклонённые</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">Дата с</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $filterDateFrom; ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">Дата по</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $filterDateTo; ?>">
                </div>
                
                <div class="form-group">
                    <label for="search">Поиск</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Имя, телефон, email..." value="<?php echo safeOutput($filterSearch); ?>">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Применить фильтры
                    </button>
                    <a href="/admin/" class="btn btn-outline">
                        <i class="fas fa-times"></i>
                        Сбросить
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Таблица заявок -->
        <div class="requests-table-container">
            <?php if (empty($leads)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Заявок не найдено</h3>
                    <p>Попробуйте изменить параметры фильтрации</p>
                </div>
            <?php else: ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td>#<?php echo $lead['id']; ?></td>
                                <td>
                                    <strong><?php echo safeOutput($lead['name']); ?></strong>
                                    <?php if (!empty($lead['comment'])): ?>
                                        <br><small style="color: #6c757d;"><?php echo safeOutput(substr($lead['comment'], 0, 50)) . '...'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="tel:<?php echo safeOutput($lead['phone']); ?>">
                                        <?php echo formatPhone($lead['phone']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($lead['email'])): ?>
                                        <a href="mailto:<?php echo safeOutput($lead['email']); ?>">
                                            <?php echo safeOutput($lead['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">не указан</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $lead['status']; ?>">
                                        <?php 
                                        $statusLabels = array(
                                            'new' => 'Новая',
                                            'processed' => 'В обработке',
                                            'completed' => 'Завершена',
                                            'rejected' => 'Отклонена'
                                        );
                                        echo isset($statusLabels[$lead['status']]) ? $statusLabels[$lead['status']] : $lead['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d.m.Y H:i', strtotime($lead['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/admin/lead_details.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-info">
    <i class="fas fa-eye"></i>
</a>
                                        <a href="?action=change_status&id=<?php echo $lead['id']; ?>&status=processed" class="btn btn-sm btn-warning">
                                            <i class="fas fa-spinner"></i>
                                        </a>
                                        <a href="?action=change_status&id=<?php echo $lead['id']; ?>&status=completed" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $lead['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Удалить заявку #<?php echo $lead['id']; ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    

</body>
</html>