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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: /admin/leads.php');
    exit;
}

$leadId = (int)$_GET['id'];
$db = getDbConnection();

$query = "SELECT * FROM leads WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$leadId]);
$lead = $stmt->fetch();

if (!$lead) {
    header('Location: /admin/leads.php?message=Заявка не найдена');
    exit;
}

$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Администратор';
$adminRole = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';

if (isset($_GET['action']) && $_GET['action'] == 'change_status' && isset($_GET['status'])) {
    $newStatus = $_GET['status'];
    $validStatuses = array('new', 'processed', 'completed', 'rejected');
    
    if (in_array($newStatus, $validStatuses)) {
        try {
            $updateStmt = $db->prepare("UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$newStatus, $leadId]);
            
            logAction('lead_status_changed', array(
                'lead_id' => $leadId,
                'new_status' => $newStatus,
                'admin_id' => $_SESSION['admin_id']
            ));
            
            $stmt->execute([$leadId]);
            $lead = $stmt->fetch();
            
            $successMessage = 'Статус заявки успешно обновлен';
        } catch (Exception $e) {
            $errorMessage = 'Ошибка при обновлении статуса';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    try {
        $deleteStmt = $db->prepare("DELETE FROM leads WHERE id = ?");
        $deleteStmt->execute([$leadId]);
        
        logAction('lead_deleted', array(
            'lead_id' => $leadId,
            'admin_id' => $_SESSION['admin_id']
        ));
        
        header('Location: /admin/leads.php?message=Заявка успешно удалена');
        exit;
    } catch (Exception $e) {
        $errorMessage = 'Ошибка при удалении заявки';
    }
}

$utmData = [];
$jsonParameters = [];
if (!empty($lead['parameters'])) {
    try {
        $jsonParameters = json_decode($lead['parameters'], true);
// ВРЕМЕННАЯ ОТЛАДКА - удалить после исправления
error_log("lead_details.php: ID заявки = " . $lead['id']);
error_log("lead_details.php: jsonParameters = " . print_r($jsonParameters, true));
$orderItems = extractOrderItems($jsonParameters);
error_log("lead_details.php: orderItems = " . print_r($orderItems, true));
error_log("lead_details.php: count orderItems = " . count($orderItems));
        if ($jsonParameters) {
            $utmData = $jsonParameters;
        }
    } catch (Exception $e) {
        error_log('Ошибка парсинга JSON параметров: ' . $e->getMessage());
    }
}

function formatSource($source) {
    if (empty($source)) return 'Не указан';
    
    $sourceLabels = [
        'yandex_direct' => '🔶 Яндекс.Директ',
        'google_ads' => '🔵 Google Ads',
        'facebook_ads' => '🔵 Facebook Ads',
        'yandex' => '🔶 Яндекс',
        'google' => '🔵 Google',
        'vkontakte' => '🔵 ВКонтакте',
        'facebook' => '🔵 Facebook',
        'instagram' => '📷 Instagram',
        'direct' => '🏠 Прямой заход',
        'organic' => '🔍 Органический поиск',
        'social' => '👥 Социальные сети',
        'referral' => '🔗 Переход с сайта',
        'email' => '✉️ Email',
        'website' => '🌐 Сайт'
    ];
    
    return $sourceLabels[$source] ?? $source;
}

function getDeviceIcon($deviceType) {
    if (empty($deviceType)) return '❓';
    
    if (stripos($deviceType, 'mobile') !== false) return '📱';
    if (stripos($deviceType, 'desktop') !== false) return '💻';
    if (stripos($deviceType, 'tablet') !== false) return '📱';
    
    return '💻';
}

function formatStatus($status) {
    $statusLabels = [
        'new' => 'Новая',
        'processed' => 'В обработке',
        'completed' => 'Завершена',
        'rejected' => 'Отклонена'
    ];
    
    return $statusLabels[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = [
        'new' => '#007bff',
        'processed' => '#ffc107',
        'completed' => '#28a745',
        'rejected' => '#dc3545'
    ];
    
    return $colors[$status] ?? '#6c757d';
}

function getTrafficTypeInfo($trafficType) {
    $info = [
        'paid_advertising' => [
            'icon' => '💸',
            'label' => 'Рекламный трафик',
            'color' => '#dc3545',
            'description' => 'Платный трафик из рекламных систем (Google Ads, Яндекс.Директ, Facebook Ads и др.)'
        ],
        'seo' => [
            'icon' => '🔍',
            'label' => 'SEO/Органический поиск',
            'color' => '#28a745',
            'description' => 'Бесплатный трафик из поисковых систем (Google, Яндекс и др.)'
        ],
        'direct' => [
            'icon' => '🏠',
            'label' => 'Прямой переход',
            'color' => '#007bff',
            'description' => 'Пользователь напрямую ввел URL сайта или использовал закладку'
        ],
        'social' => [
            'icon' => '👥',
            'label' => 'Социальные сети',
            'color' => '#17a2b8',
            'description' => 'Трафик из социальных сетей (ВКонтакте, Facebook, Instagram и др.)'
        ],
        'referral' => [
            'icon' => '🔗',
            'label' => 'Реферальный трафик',
            'color' => '#6f42c1',
            'description' => 'Переходы с других сайтов (партнерские ссылки, упоминания и т.д.)'
        ],
        'email' => [
            'icon' => '✉️',
            'label' => 'Email-рассылка',
            'color' => '#fd7e14',
            'description' => 'Переходы из email рассылок и писем'
        ],
        'telegram_bot' => [
            'icon' => '🤖',
            'label' => 'Telegram Bot',
            'color' => '#20c997',
            'description' => 'Заявки из Telegram бота'
        ],
        'unknown' => [
            'icon' => '❓',
            'label' => 'Неизвестный источник',
            'color' => '#6c757d',
            'description' => 'Не удалось определить источник трафика'
        ]
    ];
    
    return $info[$trafficType] ?? $info['unknown'];
}

function extractOrderItems($jsonParameters) {
    $orderItems = [];
    
    if (!is_array($jsonParameters)) {
        return $orderItems;
    }
    
    // Прямая проверка наличия cart_items (это наш случай!)
    if (isset($jsonParameters['cart_items']) && is_array($jsonParameters['cart_items'])) {
        error_log("extractOrderItems: Найдены cart_items, количество: " . count($jsonParameters['cart_items']));
        return $jsonParameters['cart_items']; // ПРОСТО ВОЗВРАЩАЕМ СРАЗУ
    }
    
    // Если cart_items нет, пробуем другие варианты
    if (isset($jsonParameters['items']) && is_array($jsonParameters['items'])) {
        error_log("extractOrderItems: Найдены items, количество: " . count($jsonParameters['items']));
        return $jsonParameters['items'];
    }
    
    // Ищем по числовым ключам
    foreach ($jsonParameters as $key => $value) {
        if (is_numeric($key) && is_array($value)) {
            if (isset($value['id']) || isset($value['type']) || isset($value['quantity']) || isset($value['title'])) {
                $orderItems[] = $value;
            }
        }
    }
    
    if (!empty($orderItems)) {
        error_log("extractOrderItems: Найдены товары по числовым ключам, количество: " . count($orderItems));
        return $orderItems;
    }
    
    // Проверяем одиночный товар
    if (isset($jsonParameters['id']) || isset($jsonParameters['type']) || isset($jsonParameters['quantity'])) {
        error_log("extractOrderItems: Найден одиночный товар");
        return [$jsonParameters];
    }
    
    error_log("extractOrderItems: Товары не найдены");
    return [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка #<?php echo $lead['id']; ?> - ZLOCK</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        <?php include 'normalize.css'; ?>
        
        .lead-details-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .lead-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .lead-title {
            margin: 0;
            color: #041c2c;
        }
        
        .lead-meta {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .lead-actions {
            display: flex;
            gap: 10px;
        }
        
        .details-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .detail-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #041c2c;
            font-size: 18px;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #041c2c;
            word-break: break-word;
        }
        
        .detail-value a {
            color: #007bff;
            text-decoration: none;
        }
        
        .detail-value a:hover {
            text-decoration: underline;
        }
        
        .comment-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            white-space: pre-wrap;
            line-height: 1.5;
            font-size: 14px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .back-button:hover {
            background: #5a6268;
        }
        
        .json-debug {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 20px;
            display: none;
        }
        
        .json-debug.show {
            display: block;
        }
        
        .toggle-debug-btn {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            color: #6c757d;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        
        .toggle-debug-btn:hover {
            background: #e9ecef;
        }
        
        .status-badge-large {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .traffic-type-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .traffic-type-icon {
            font-size: 24px;
        }
        
        .traffic-type-text {
            flex: 1;
        }
        
        .traffic-type-title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 2px;
        }
        
        .traffic-type-description {
            font-size: 12px;
            color: #6c757d;
        }
        
        .visit-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            background: #e3f2fd;
            border-radius: 4px;
            color: #1976d2;
            text-decoration: none;
            font-size: 12px;
        }
        
        .visit-link:hover {
            background: #bbdefb;
        }
        
        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .order-items-table th {
            padding: 10px;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            text-align: left;
        }
        
        .order-items-table td {
            padding: 10px;
            border: 1px solid #dee2e6;
        }
        
        .order-items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .price-cell {
            text-align: right;
            font-weight: 600;
        }
        
        .quantity-cell {
            text-align: center;
            font-weight: 600;
        }
        
        .total-row {
            background: #f8f9fa !important;
            font-weight: 700;
            border-top: 2px solid #dee2e6;
        }
        
        .total-amount {
            color: #4caf50;
            font-size: 16px;
        }
        
        .total-quantity {
            color: #2196f3;
            font-size: 16px;
        }
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
        <div class="lead-details-container">
            <a href="/admin/leads.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Назад к списку заявок
            </a>
            
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success">
                    <?php echo safeOutput($successMessage); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger">
                    <?php echo safeOutput($errorMessage); ?>
                </div>
            <?php endif; ?>
            
            <div class="lead-header">
                <div>
                    <h1 class="lead-title">Заявка #<?php echo $lead['id']; ?></h1>
                    <p>Подробная информация о заявке от клиента</p>
                </div>
                
                <div class="lead-meta">
                    <span class="status-badge-large" style="background: <?php echo getStatusColor($lead['status']); ?>; color: white;">
                        <?php echo formatStatus($lead['status']); ?>
                    </span>
                    
                    <div class="lead-actions">
                        <a href="?id=<?php echo $lead['id']; ?>&action=change_status&status=processed" 
                           class="btn btn-warning" 
                           title="В обработку">
                            <i class="fas fa-spinner"></i>
                        </a>
                        <a href="?id=<?php echo $lead['id']; ?>&action=change_status&status=completed" 
                           class="btn btn-success"
                           title="Завершить">
                            <i class="fas fa-check"></i>
                        </a>
                        <a href="?id=<?php echo $lead['id']; ?>&action=change_status&status=rejected" 
                           class="btn btn-danger"
                           title="Отклонить">
                            <i class="fas fa-times"></i>
                        </a>
                        <a href="?id=<?php echo $lead['id']; ?>&action=delete" 
                           class="btn btn-danger"
                           onclick="return confirm('Вы уверены, что хотите удалить заявку #<?php echo $lead['id']; ?>?')"
                           title="Удалить">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="details-grid">
                <div class="detail-card" style="border-left-color: #007bff;">
                    <h3><i class="fas fa-user"></i> Контактная информация</h3>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <span class="detail-label">Имя</span>
                            <span class="detail-value"><?php echo safeOutput($lead['name']); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Телефон</span>
                            <span class="detail-value">
                                <a href="tel:<?php echo safeOutput($lead['phone']); ?>">
                                    <?php echo formatPhone($lead['phone']); ?>
                                </a>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value">
                                <?php if (!empty($lead['email'])): ?>
                                    <a href="mailto:<?php echo safeOutput($lead['email']); ?>">
                                        <?php echo safeOutput($lead['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #6c757d;">не указан</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($lead['ip_address'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">IP-адрес</span>
                            <span class="detail-value" style="font-family: monospace;">
                                <?php echo safeOutput($lead['ip_address']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <span class="detail-label">Тип заявки</span>
                            <span class="detail-value">
                                <span style="padding: 2px 8px; background: #e9ecef; border-radius: 4px; font-size: 12px;">
                                    <?php echo $lead['type'] ?? 'form'; ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Дата создания</span>
                            <span class="detail-value">
                                <?php echo date('d.m.Y H:i', strtotime($lead['created_at'])); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($lead['updated_at'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Дата обновления</span>
                            <span class="detail-value">
                                <?php echo date('d.m.Y H:i', strtotime($lead['updated_at'])); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php 
                $trafficType = $utmData['traffic_type'] ?? 'unknown';
                $trafficInfo = getTrafficTypeInfo($trafficType);
                ?>
                <div class="detail-card" style="border-left-color: <?php echo $trafficInfo['color']; ?>;">
                    <div class="traffic-type-info">
                        <div class="traffic-type-icon"><?php echo $trafficInfo['icon']; ?></div>
                        <div class="traffic-type-text">
                            <div class="traffic-type-title"><?php echo $trafficInfo['label']; ?></div>
                            <div class="traffic-type-description"><?php echo $trafficInfo['description']; ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <?php if (!empty($utmData['visit_id'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Связанный визит</span>
                            <span class="detail-value">
                                <a href="/admin/lead_details.php?id=<?php echo $utmData['visit_id']; ?>" class="visit-link">
                                    <i class="fas fa-external-link-alt"></i>
                                    Посещение #<?php echo $utmData['visit_id']; ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($utmData['first_visit'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Первое посещение</span>
                            <span class="detail-value">
                                <?php echo date('d.m.Y H:i', strtotime($utmData['first_visit'])); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>













<?php 
// Извлекаем товары из параметров
$orderItems = extractOrderItems($jsonParameters);

if (!empty($orderItems)): 
    $hasMultipleItems = count($orderItems) > 1;
    $totalItems = 0;
    $totalAmount = 0;
    
    // Сначала считаем итоги
    foreach ($orderItems as $item) {
        $itemQuantity = (int)($item['quantity'] ?? 0);
        $itemPrice = null;
        
        // Определяем цену
        if (isset($item['prices']) && is_array($item['prices'])) {
            $prices = $item['prices'];
            if ($itemQuantity >= 300000 && isset($prices['opt300k'])) {
                $itemPrice = (float)$prices['opt300k'];
            } elseif ($itemQuantity >= 20000 && isset($prices['opt20k'])) {
                $itemPrice = (float)$prices['opt20k'];
            } elseif ($itemQuantity >= 3000 && isset($prices['retail'])) {
                $itemPrice = (float)$prices['retail'];
            }
        }
        
        $itemTotal = $itemPrice ? $itemPrice * $itemQuantity : 0;
        $totalItems += $itemQuantity;
        $totalAmount += $itemTotal;
    }
?>
<div class="detail-card" style="border-left-color: #9c27b0;">
    <h3><i class="fas fa-shopping-cart"></i> Параметры заказа</h3>
    
    <?php if ($hasMultipleItems): ?>
        <h4 style="margin-bottom: 15px; color: #666; font-size: 16px;">
            <i class="fas fa-boxes"></i> Товары в заказе (<?php echo count($orderItems); ?>):
        </h4>
    <?php else: ?>
        <h4 style="margin-bottom: 15px; color: #666; font-size: 16px;">
            <i class="fas fa-box"></i> Товар в заказе:
        </h4>
    <?php endif; ?>
    
    <div style="overflow-x: auto; margin-bottom: 20px;">
        <table class="order-items-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Товар</th>
                    <th style="width: 15%; text-align: center;">Размер</th>
                    <th style="width: 15%; text-align: center;">Толщина</th>
                    <th style="width: 15%; text-align: center;">Кол-во</th>
                    <th style="width: 12%; text-align: right;">Цена</th>
                    <th style="width: 13%; text-align: right;">Сумма</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item): 
                    $itemQuantity = (int)($item['quantity'] ?? 0);
                    $itemPrice = null;
                    
                    // Определяем цену
                    if (isset($item['prices']) && is_array($item['prices'])) {
                        $prices = $item['prices'];
                        if ($itemQuantity >= 300000 && isset($prices['opt300k'])) {
                            $itemPrice = (float)$prices['opt300k'];
                        } elseif ($itemQuantity >= 20000 && isset($prices['opt20k'])) {
                            $itemPrice = (float)$prices['opt20k'];
                        } elseif ($itemQuantity >= 3000 && isset($prices['retail'])) {
                            $itemPrice = (float)$prices['retail'];
                        }
                    }
                    
                    $itemTotal = $itemPrice ? $itemPrice * $itemQuantity : 0;
                    
                    // Определяем название товара
                    $itemName = '';
                    if (!empty($item['type'])) {
                        $itemName = $item['type'];
                    } elseif (!empty($item['title'])) {
                        $itemName = $item['title'];
                    } else {
                        $itemName = 'Товар';
                    }
                    
                    // Определяем размер
                    $itemSize = '';
                    if (!empty($item['size'])) {
                        $itemSize = $item['size'];
                    } elseif (!empty($item['width']) && !empty($item['height'])) {
                        $itemSize = $item['width'] . ' × ' . $item['height'] . ' см';
                    }
                    
                    // Определяем толщину
                    $itemThickness = $item['thickness'] ?? '';
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($itemName); ?></strong>
                        <?php if (!empty($item['id'])): ?>
                        <br><small style="color: #999; font-size: 11px;">ID: <?php echo htmlspecialchars(substr($item['id'], 0, 20)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if (!empty($itemSize)): ?>
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($itemSize); ?></span>
                        <?php else: ?>
                        <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if (!empty($itemThickness)): ?>
                        <span style="display: inline-block; padding: 2px 8px; background: #e3f2fd; border-radius: 12px; font-weight: 600;">
                            <?php echo htmlspecialchars($itemThickness); ?>
                        </span>
                        <?php else: ?>
                        <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="quantity-cell">
                        <?php echo number_format($itemQuantity, 0, ',', ' '); ?> шт
                    </td>
                    <td class="price-cell">
                        <?php if ($itemPrice): ?>
                        <?php echo number_format($itemPrice, 2, ',', ' '); ?> ₽
                        <?php else: ?>
                        <span style="color: #999; font-style: italic;">не указана</span>
                        <?php endif; ?>
                    </td>
                    <td class="price-cell">
                        <?php if ($itemTotal > 0): ?>
                        <strong><?php echo number_format($itemTotal, 2, ',', ' '); ?> ₽</strong>
                        <?php else: ?>
                        <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td colspan="3" style="text-align: right; padding-right: 20px;">
                        <strong>Итого:</strong>
                    </td>
                    <td class="quantity-cell total-quantity">
                        <?php echo number_format($totalItems, 0, ',', ' '); ?> шт
                    </td>
                    <td style="text-align: center;">
                        <span style="color: #999;">—</span>
                    </td>
                    <td class="price-cell total-amount">
                        <?php if ($totalAmount > 0): ?>
                        <strong><?php echo number_format($totalAmount, 2, ',', ' '); ?> ₽</strong>
                        <?php else: ?>
                        <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

                    
                    <!-- Дополнительная информация о заказе -->
                    <?php 
                    $hasOrderInfo = false;
                    $orderInfoFields = ['material', 'print_type', 'delivery_type', 'color', 'packaging'];
                    
                    foreach ($orderInfoFields as $field) {
                        if (!empty($jsonParameters[$field])) {
                            $hasOrderInfo = true;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($hasOrderInfo): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin-top: 0; margin-bottom: 10px; color: #666; font-size: 15px;">
                            <i class="fas fa-info-circle"></i> Дополнительная информация:
                        </h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                            <?php foreach ($orderInfoFields as $field): 
                                if (!empty($jsonParameters[$field])): ?>
                                <div>
                                    <small style="color: #999;">
                                        <?php 
                                        $fieldLabels = [
                                            'material' => 'Материал',
                                            'print_type' => 'Тип печати',
                                            'delivery_type' => 'Доставка',
                                            'color' => 'Цвет',
                                            'packaging' => 'Упаковка'
                                        ];
                                        echo $fieldLabels[$field] ?? $field;
                                        ?>:
                                    </small>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($jsonParameters[$field]); ?></div>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="detail-card" style="border-left-color: #28a745;">
                    <h3><i class="fas fa-chart-line"></i> UTM-метки и источник трафика</h3>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <span class="detail-label">Источник</span>
                            <span class="detail-value">
                                <?php echo formatSource($lead['source'] ?? $utmData['traffic_source'] ?? ''); ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($lead['utm_source']) || !empty($utmData['utm_source'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">UTM Source</span>
                            <span class="detail-value" style="color: #28a745;">
                                <?php echo safeOutput($lead['utm_source'] ?? $utmData['utm_source'] ?? ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['utm_medium']) || !empty($utmData['utm_medium'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">UTM Medium</span>
                            <span class="detail-value" style="color: #17a2b8;">
                                <?php echo safeOutput($lead['utm_medium'] ?? $utmData['utm_medium'] ?? ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-row">
                        <?php if (!empty($lead['utm_campaign']) || !empty($utmData['utm_campaign'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">UTM Campaign</span>
                            <span class="detail-value" style="color: #dc3545;">
                                <?php echo safeOutput($lead['utm_campaign'] ?? $utmData['utm_campaign'] ?? ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['utm_term']) || !empty($utmData['utm_term'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">UTM Term</span>
                            <span class="detail-value" style="color: #ffc107;">
                                <?php echo safeOutput($lead['utm_term'] ?? $utmData['utm_term'] ?? ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['utm_content']) || !empty($utmData['utm_content'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">UTM Content</span>
                            <span class="detail-value" style="color: #6f42c1;">
                                <?php echo safeOutput($lead['utm_content'] ?? $utmData['utm_content'] ?? ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-card" style="border-left-color: #20c997;">
                    <h3><i class="fas fa-search"></i> Поиск и устройство</h3>
                    
                    <div class="detail-row">
                        <?php if (!empty($lead['search_engine']) || !empty($utmData['search_engine'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Поисковая система</span>
                            <span class="detail-value">
                                <?php 
                                $searchEngine = $lead['search_engine'] ?? $utmData['search_engine'] ?? '';
                                if ($searchEngine === 'google') {
                                    echo '🔵 Google';
                                } elseif ($searchEngine === 'yandex') {
                                    echo '🔶 Яндекс';
                                } else {
                                    echo safeOutput($searchEngine);
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['search_query_cache']) || !empty($lead['search_query_virtual']) || !empty($utmData['search_query'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Поисковый запрос</span>
                            <span class="detail-value" style="color: #20c997;">
                                "<?php echo safeOutput($lead['search_query_cache'] ?? $lead['search_query_virtual'] ?? $utmData['search_query'] ?? ''); ?>"
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['device_type']) || !empty($utmData['device'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Устройство</span>
                            <span class="detail-value">
                                <?php 
                                $deviceType = $lead['device_type'] ?? $utmData['device'] ?? '';
                                echo getDeviceIcon($deviceType) . ' ';
                                
                                if ($deviceType === 'mobile') {
                                    echo 'Мобильное';
                                } elseif ($deviceType === 'desktop') {
                                    echo 'Десктоп';
                                } elseif ($deviceType === 'tablet') {
                                    echo 'Планшет';
                                } else {
                                    echo safeOutput($deviceType);
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($lead['is_bot']) || isset($utmData['is_bot'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Бот</span>
                            <span class="detail-value" style="color: <?php echo ($lead['is_bot'] == 1 || ($utmData['is_bot'] ?? false)) ? '#dc3545' : '#28a745'; ?>">
                                <?php echo ($lead['is_bot'] == 1 || ($utmData['is_bot'] ?? false)) ? '🤖 Да' : '👤 Нет'; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-card" style="border-left-color: #6f42c1;">
                    <h3><i class="fas fa-globe"></i> Посещение сайта</h3>
                    
                    <div class="detail-row">
                        <?php if (!empty($lead['referrer']) || !empty($utmData['referrer'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Referrer (откуда пришел)</span>
                            <span class="detail-value">
                                <a href="<?php echo safeOutput($lead['referrer'] ?? $utmData['referrer'] ?? ''); ?>" 
                                   target="_blank" 
                                   style="word-break: break-all;">
                                    <?php echo safeOutput($lead['referrer'] ?? $utmData['referrer'] ?? ''); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['landing_page']) || !empty($utmData['landing_page'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Страница входа</span>
                            <span class="detail-value" style="word-break: break-all;">
                                <?php echo safeOutput($lead['landing_page'] ?? $utmData['landing_page'] ?? ''); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-row">
                        <?php if (!empty($lead['first_visit']) || !empty($utmData['first_visit'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Первый визит</span>
                            <span class="detail-value">
                                <?php 
                                $firstVisit = $lead['first_visit'] ?? $utmData['first_visit'] ?? '';
                                if (!empty($firstVisit)) {
                                    echo date('d.m.Y H:i', strtotime($firstVisit));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['utm_first_seen'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Время первого UTM</span>
                            <span class="detail-value">
                                <?php echo date('d.m.Y H:i', strtotime($lead['utm_first_seen'])); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="detail-card" style="border-left-color: #fd7e14;">
                    <h3><i class="fas fa-mouse-pointer"></i> Клик ID (Рекламные системы)</h3>
                    
                    <div class="detail-row">
                        <?php if (!empty($lead['gclid'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Google Click ID</span>
                            <span class="detail-value" style="font-family: monospace; color: #4285f4;">
                                <?php echo safeOutput($lead['gclid']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['yclid'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Yandex Click ID</span>
                            <span class="detail-value" style="font-family: monospace; color: #fc3f1d;">
                                <?php echo safeOutput($lead['yclid']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lead['fbclid'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Facebook Click ID</span>
                            <span class="detail-value" style="font-family: monospace; color: #1877f2;">
                                <?php echo safeOutput($lead['fbclid']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($lead['comment'])): ?>
                <div class="detail-card" style="border-left-color: #e83e8c;">
                    <h3><i class="fas fa-comment"></i> Комментарий</h3>
                    <div class="comment-box">
                        <?php echo nl2br(safeOutput($lead['comment'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <button class="toggle-debug-btn" onclick="toggleDebug()">
                <i class="fas fa-code"></i> Показать/скрыть JSON данные для отладки
            </button>
            
            <div class="json-debug" id="jsonDebug">
                <h4 style="margin-top: 0; color: #d4d4d4;">JSON параметры:</h4>
                <pre><?php 
                if (!empty($jsonParameters)) {
                    echo htmlspecialchars(json_encode($jsonParameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } else {
                    echo 'Нет JSON данных';
                }
                ?></pre>
                
                <h4 style="color: #d4d4d4;">Все данные заявки:</h4>
                <pre><?php 
                $leadData = $lead;
                unset($leadData['parameters']);
                echo htmlspecialchars(json_encode($leadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                ?></pre>
            </div>
        </div>
    </div>

    <script>
    function toggleDebug() {
        const debugElement = document.getElementById('jsonDebug');
        debugElement.classList.toggle('show');
    }
    
    setInterval(function() {
        if (!document.hidden) {
            window.location.reload();
        }
    }, 5 * 60 * 1000);
    </script>
</body>
</html>