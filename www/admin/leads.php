<?php
require_once '../includes/init.php';
require_once 'includes/security_config.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

checkAdminAuth();

$current_page = basename($_SERVER['PHP_SELF']);
checkPageAccess($current_page);

if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    requirePermission('delete_leads');
}

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$db = getDbConnection();

$trafficIcons = [
    'paid_advertising' => '💸',
    'seo' => '🔍',
    'direct' => '🏠',
    'social' => '👥',
    'referral' => '🔗',
    'email' => '✉️',
    'telegram_bot' => '🤖',
    'unknown' => '❓'
];

$trafficLabels = [
    'paid_advertising' => 'Реклама',
    'seo' => 'SEO',
    'direct' => 'Прямой',
    'social' => 'Соцсети',
    'referral' => 'Реф.',
    'email' => 'Email',
    'telegram_bot' => 'Telegram',
    'unknown' => 'Неизв.'
];

// Получаем параметры фильтрации
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$trafficTypeFilter = isset($_GET['traffic_type']) ? $_GET['traffic_type'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ПОКАЗЫВАЕМ ТОЛЬКО ЛИДЫ (НЕ ПОСЕЩЕНИЯ)
$sql = "SELECT * FROM leads WHERE 
        (type != 'visit' OR type IS NULL) AND 
        (name != 'Посетитель' OR name IS NULL) AND 
        (phone != '' OR phone IS NOT NULL)";

$countSql = "SELECT COUNT(*) as total FROM leads WHERE 
            (type != 'visit' OR type IS NULL) AND 
            (name != 'Посетитель' OR name IS NULL) AND 
            (phone != '' OR phone IS NOT NULL)";
$params = [];
$countParams = [];


// Формируем базовый SQL запрос
$sql = "SELECT * FROM leads WHERE 1=1";
$countSql = "SELECT COUNT(*) as total FROM leads WHERE 1=1";
$params = [];
$countParams = [];

// Фильтр по типу трафика
if (!empty($trafficTypeFilter)) {
    $sql .= " AND JSON_EXTRACT(parameters, '$.traffic_type') = ?";
    $countSql .= " AND JSON_EXTRACT(parameters, '$.traffic_type') = ?";
    $params[] = $trafficTypeFilter;
    $countParams[] = $trafficTypeFilter;
}

// Фильтр по статусу
if (!empty($statusFilter)) {
    $sql .= " AND status = ?";
    $countSql .= " AND status = ?";
    $params[] = $statusFilter;
    $countParams[] = $statusFilter;
}

// Фильтр по поиску
if (!empty($searchQuery)) {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR comment LIKE ?)";
    $countSql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR comment LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

// Фильтр по дате
if (!empty($dateFrom)) {
    $sql .= " AND DATE(created_at) >= ?";
    $countSql .= " AND DATE(created_at) >= ?";
    $params[] = $dateFrom;
    $countParams[] = $dateFrom;
}
if (!empty($dateTo)) {
    $sql .= " AND DATE(created_at) <= ?";
    $countSql .= " AND DATE(created_at) <= ?";
    $params[] = $dateTo;
    $countParams[] = $dateTo;
}

// Сортировка и пагинация
$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

// Получаем общее количество заявок с учетом фильтров
$totalStmt = $db->prepare($countSql);
$totalStmt->execute($countParams);
$total = $totalStmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// Получаем заявки для текущей страницы
$stmt = $db->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Получаем информацию об администраторе для отображения
$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Администратор';
$adminRole = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Все заявки - ZLOCK</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        <?php include 'normalize.css'; ?>
        
        /* Дополнительные стили для фильтра по типу трафика */
        .traffic-type-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .traffic-type-btn {
            padding: 8px 16px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .traffic-type-btn:hover {
            background: #f8f9fa;
        }
        
        .traffic-type-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .traffic-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .traffic-stat-item {
            display: flex;
            flex-direction: column;
        }
        
        .traffic-stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #041c2c;
        }
        
        .traffic-stat-label {
            font-size: 12px;
            color: #6c757d;
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
            
            <a href='/admin/leads.php' class='menu-item active'>
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
            
            <!-- Новая ссылка на статистику -->
            <a href='/admin/statistics.php' class='menu-item'>
                <div class='fa-icon'><i class="fas fa-chart-pie"></i></div>
                <div class='menu-text'>
                    <strong>Статистика</strong>
                    <p>Анализ трафика и конверсий</p>
                </div>
            </a>
        </div>
    </div>
    
    <div class='content-container'>
        <div class='content-header'>
            <h1>Все заявки (<?php echo $total; ?>)</h1>
            <p>Управление заявками от клиентов</p>
        </div>
        
        <!-- Статистика по типам трафика -->
        <?php if ($total > 0): ?>
        <div class="traffic-stats">
            <?php
            // Получаем статистику по типам трафика
            $statsSql = "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.traffic_type')) as traffic_type,
                COUNT(*) as count
                FROM leads 
                WHERE 1=1";
            
            $statsParams = [];
            
            // Добавляем те же фильтры что и в основной запрос
            if (!empty($trafficTypeFilter)) {
                $statsSql .= " AND JSON_EXTRACT(parameters, '$.traffic_type') = ?";
                $statsParams[] = $trafficTypeFilter;
            }
            if (!empty($statusFilter)) {
                $statsSql .= " AND status = ?";
                $statsParams[] = $statusFilter;
            }
            if (!empty($searchQuery)) {
                $statsSql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR comment LIKE ?)";
                $searchParam = "%$searchQuery%";
                $statsParams[] = $searchParam;
                $statsParams[] = $searchParam;
                $statsParams[] = $searchParam;
                $statsParams[] = $searchParam;
            }
            if (!empty($dateFrom)) {
                $statsSql .= " AND DATE(created_at) >= ?";
                $statsParams[] = $dateFrom;
            }
            if (!empty($dateTo)) {
                $statsSql .= " AND DATE(created_at) <= ?";
                $statsParams[] = $dateTo;
            }
            
            $statsSql .= " GROUP BY JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.traffic_type'))";
            
            $statsStmt = $db->prepare($statsSql);
            $statsStmt->execute($statsParams);
            $trafficStats = $statsStmt->fetchAll();
            
            $trafficIcons = [
                'paid_advertising' => '💸',
                'seo' => '🔍',
                'direct' => '🏠',
                'social' => '👥',
                'referral' => '🔗',
                'email' => '✉️',
                'telegram_bot' => '🤖',
                'unknown' => '❓'
            ];
            
            $trafficLabels = [
                'paid_advertising' => 'Реклама',
                'seo' => 'SEO',
                'direct' => 'Прямой',
                'social' => 'Соцсети',
                'referral' => 'Реф.',
                'email' => 'Email',
                'telegram_bot' => 'Telegram',
                'unknown' => 'Неизв.'
            ];
            
            foreach ($trafficStats as $stat):
                $type = $stat['traffic_type'] ?: 'unknown';
                $icon = $trafficIcons[$type] ?? '❓';
                $label = $trafficLabels[$type] ?? 'Неизвестно';
                $count = $stat['count'];
                $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            ?>
            <div class="traffic-stat-item">
                <span class="traffic-stat-value">
                    <?php echo $icon . ' ' . $count; ?>
                </span>
                <span class="traffic-stat-label">
                    <?php echo $label . ' (' . $percentage . '%)'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Быстрые фильтры по типу трафика -->
<?php
// Определяем массивы для фильтров ДО их использования
$trafficIcons = [
    'paid_advertising' => '💸',
    'seo' => '🔍',
    'direct' => '🏠',
    'social' => '👥',
    'referral' => '🔗',
    'email' => '✉️',
    'telegram_bot' => '🤖',
    'unknown' => '❓'
];

$trafficLabels = [
    'paid_advertising' => 'Реклама',
    'seo' => 'SEO',
    'direct' => 'Прямой',
    'social' => 'Соцсети',
    'referral' => 'Реф.',
    'email' => 'Email',
    'telegram_bot' => 'Telegram',
    'unknown' => 'Неизв.'
];
?>

<!-- Быстрые фильтры по типу трафика -->
<div class="traffic-type-filter">
    <a href="?<?php 
        $query = $_GET;
        unset($query['traffic_type']);
        echo http_build_query($query);
    ?>" class="traffic-type-btn <?php echo empty($trafficTypeFilter) ? 'active' : ''; ?>">
        <i class="fas fa-globe"></i> Все типы
    </a>
    
    <?php foreach ($trafficLabels as $type => $label): 
        $icon = $trafficIcons[$type] ?? '❓';
        $isActive = $trafficTypeFilter === $type;
    ?>
    <a href="?<?php 
        $query = $_GET;
        $query['traffic_type'] = $type;
        echo http_build_query($query);
    ?>" class="traffic-type-btn <?php echo $isActive ? 'active' : ''; ?>">
        <?php echo $icon . ' ' . $label; ?>
    </a>
    <?php endforeach; ?>
</div>
        
        <div class="filters">
            <h3>Фильтры заявок</h3>
            <form method="GET" class="filter-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="form-group">
                    <label for="traffic_type">Тип трафика</label>
                    <select id="traffic_type" name="traffic_type" class="form-control">
                        <option value="">Все типы</option>
                        <option value="paid_advertising" <?php echo $trafficTypeFilter === 'paid_advertising' ? 'selected' : ''; ?>>💸 Рекламный</option>
                        <option value="seo" <?php echo $trafficTypeFilter === 'seo' ? 'selected' : ''; ?>>🔍 SEO/Органический</option>
                        <option value="direct" <?php echo $trafficTypeFilter === 'direct' ? 'selected' : ''; ?>>🏠 Прямые переходы</option>
                        <option value="social" <?php echo $trafficTypeFilter === 'social' ? 'selected' : ''; ?>>👥 Социальные сети</option>
                        <option value="referral" <?php echo $trafficTypeFilter === 'referral' ? 'selected' : ''; ?>>🔗 Реферальный</option>
                        <option value="email" <?php echo $trafficTypeFilter === 'email' ? 'selected' : ''; ?>>✉️ Email</option>
                        <option value="telegram_bot" <?php echo $trafficTypeFilter === 'telegram_bot' ? 'selected' : ''; ?>>🤖 Telegram Bot</option>
                        <option value="unknown" <?php echo $trafficTypeFilter === 'unknown' ? 'selected' : ''; ?>>❓ Неизвестный</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Статус</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Все статусы</option>
                        <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>Новые</option>
                        <option value="processed" <?php echo $statusFilter === 'processed' ? 'selected' : ''; ?>>В обработке</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Завершённые</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Отклонённые</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">Дата с</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="date_to">Дата по</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="search">Поиск</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Имя, телефон, email..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Применить фильтры
                    </button>
                    <a href="/admin/leads.php" class="btn btn-outline">
                        <i class="fas fa-times"></i>
                        Сбросить
                    </a>
                </div>
            </form>
        </div>
        
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success">
                <?php echo safeOutput($_GET['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="requests-table-container">
            <?php if (empty($leads)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Заявок нет</h3>
                    <p>Начните работу с системой, чтобы здесь появились заявки</p>
                </div>
            <?php else: ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Имя</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Тип</th>
                            <th>Источник</th>
                            <th>Тип трафика</th>
                            <th>Статус</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): 
                            $parameters = !empty($lead['parameters']) ? json_decode($lead['parameters'], true) : [];
                            $trafficType = $parameters['traffic_type'] ?? 'unknown';
                            
                            $trafficIcons = [
                                'paid_advertising' => '💸',
                                'seo' => '🔍',
                                'direct' => '🏠',
                                'social' => '👥',
                                'referral' => '🔗',
                                'email' => '✉️',
                                'telegram_bot' => '🤖',
                                'unknown' => '❓'
                            ];
                            
                            $trafficLabels = [
                                'paid_advertising' => 'Реклама',
                                'seo' => 'SEO',
                                'direct' => 'Прямой',
                                'social' => 'Соцсети',
                                'referral' => 'Реф.',
                                'email' => 'Email',
                                'telegram_bot' => 'Telegram',
                                'unknown' => 'Неизв.'
                            ];
                            
                            $icon = $trafficIcons[$trafficType] ?? '❓';
                            $label = $trafficLabels[$trafficType] ?? 'Неизвестно';
                            
                            $source = $lead['source'] ?? 'website';
                            $sourceIcons = [
                                'yandex_direct' => '🔶',
                                'google_ads' => '🔵',
                                'facebook_ads' => '🔵',
                                'yandex' => '🔶',
                                'google' => '🔵',
                                'vkontakte' => '🔵',
                                'direct' => '🏠',
                                'organic' => '🔍',
                                'telegram_bot' => '🤖'
                            ];
                            $sourceIcon = $sourceIcons[$source] ?? '🌐';
                        ?>
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
                                <span style="font-size: 11px; padding: 2px 6px; background: #e9ecef; border-radius: 4px;">
                                    <?php echo $lead['type'] ?? 'form'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $sourceIcon . ' ' . safeOutput($source); ?>
                            </td>
                            <td>
                                <span title="<?php echo $trafficType; ?>" style="cursor: help;">
                                    <?php echo $icon . ' ' . $label; ?>
                                </span>
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
                                    <a href="/admin/?action=change_status&id=<?php echo $lead['id']; ?>&status=processed" class="btn btn-sm btn-warning">
                                        <i class="fas fa-spinner"></i>
                                    </a>
                                    <a href="/admin/?action=change_status&id=<?php echo $lead['id']; ?>&status=completed" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="/admin/?action=delete&id=<?php echo $lead['id']; ?>" 
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
                
                <!-- Пагинация -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php 
                            $query = $_GET;
                            $query['page'] = $page - 1;
                            echo http_build_query($query);
                        ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Назад
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?<?php 
                            $query = $_GET;
                            $query['page'] = $i;
                            echo http_build_query($query);
                        ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php 
                            $query = $_GET;
                            $query['page'] = $page + 1;
                            echo http_build_query($query);
                        ?>" class="page-link">
                            Вперед <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>