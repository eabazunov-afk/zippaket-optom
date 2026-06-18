<?php
require_once '../includes/init.php';
require_once 'includes/security_config.php';
require_once 'includes/auth.php';
require_once 'includes/permissions.php';

checkAdminAuth();
checkPageAccess('statistics.php');

$db = getDbConnection();

// Параметры периода
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Устанавливаем время на конец дня для dateTo
$dateToEnd = $dateTo . ' 23:59:59';

// ============================================
// 1. ОБЩАЯ СТАТИСТИКА
// ============================================

// Статистика визитов из таблицы visits
$visitsQuery = "
    SELECT 
        COUNT(*) as total_visits,
        COUNT(DISTINCT session_id) as unique_visitors,
        COUNT(DISTINCT ip_address) as unique_ips
    FROM visits 
    WHERE DATE(visit_date) BETWEEN ? AND ?
";

$stmt = $db->prepare($visitsQuery);
$stmt->execute([$dateFrom, $dateTo]);
$visitsStats = $stmt->fetch();

// Статистика заявок из таблицы leads (исключаем визиты)
$leadsQuery = "
    SELECT 
        COUNT(*) as total_leads,
        COUNT(DISTINCT CASE WHEN status = 'new' THEN id END) as new_leads,
        COUNT(DISTINCT CASE WHEN status = 'processed' THEN id END) as processed_leads,
        COUNT(DISTINCT CASE WHEN status = 'completed' THEN id END) as completed_leads
    FROM leads 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND (type != 'visit' OR type IS NULL)
    AND (name != 'Посетитель' OR name IS NULL)
";

$stmt = $db->prepare($leadsQuery);
$stmt->execute([$dateFrom, $dateTo]);
$leadsStats = $stmt->fetch();

// Расчет конверсии
$totalVisits = isset($visitsStats['total_visits']) ? (int)$visitsStats['total_visits'] : 0;
$totalLeads = isset($leadsStats['total_leads']) ? (int)$leadsStats['total_leads'] : 0;
$conversionRate = $totalVisits > 0 ? round(($totalLeads / $totalVisits) * 100, 2) : 0;

// ============================================
// 2. СТАТИСТИКА ПО ТИПАМ ТРАФИКА (из visits)
// ============================================

$trafficQuery = "
    SELECT 
        traffic_source,
        COUNT(*) as visits,
        COUNT(DISTINCT session_id) as unique_visits,
        COUNT(DISTINCT 
            CASE WHEN lead_id IS NOT NULL THEN session_id END
        ) as converted_visits
    FROM visits 
    WHERE DATE(visit_date) BETWEEN ? AND ?
    GROUP BY traffic_source
    ORDER BY visits DESC
";

$stmt = $db->prepare($trafficQuery);
$stmt->execute([$dateFrom, $dateTo]);
$trafficStats = $stmt->fetchAll();

// Получаем заявки по источникам (из leads)
$leadsBySourceQuery = "
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.traffic_source')) as traffic_source,
        COUNT(*) as leads_count
    FROM leads 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND (type != 'visit' OR type IS NULL)
    AND (name != 'Посетитель' OR name IS NULL)
    GROUP BY JSON_UNQUOTE(JSON_EXTRACT(parameters, '$.traffic_source'))
";

$stmt = $db->prepare($leadsBySourceQuery);
$stmt->execute([$dateFrom, $dateTo]);
$leadsBySource = [];
while ($row = $stmt->fetch()) {
    $source = isset($row['traffic_source']) ? $row['traffic_source'] : '';
    $leadsBySource[$source] = isset($row['leads_count']) ? (int)$row['leads_count'] : 0;
}

// Объединяем данные
$trafficData = [];
foreach ($trafficStats as $stat) {
    $source = isset($stat['traffic_source']) ? $stat['traffic_source'] : 'direct';
    // Заменяем null на пустую строку для безопасности
    $safeSource = $source ?: 'direct';
    
    $visits = isset($stat['visits']) ? (int)$stat['visits'] : 0;
    $uniqueVisits = isset($stat['unique_visits']) ? (int)$stat['unique_visits'] : 0;
    $leadsCount = isset($leadsBySource[$safeSource]) ? (int)$leadsBySource[$safeSource] : 0;
    $conversion = $visits > 0 ? round(($leadsCount / $visits) * 100, 2) : 0;
    
    $trafficData[] = [
        'source' => $safeSource,
        'visits' => $visits,
        'unique_visits' => $uniqueVisits,
        'leads' => $leadsCount,
        'conversion' => $conversion,
        'total' => $visits + $leadsCount
    ];
}

// ============================================
// 3. СТАТИСТИКА ПО ДНЯМ
// ============================================

$dailyQuery = "
    SELECT 
        DATE(v.visit_date) as date,
        COUNT(DISTINCT v.id) as visits,
        COUNT(DISTINCT v.session_id) as unique_visits,
        COUNT(DISTINCT l.id) as leads
    FROM visits v
    LEFT JOIN leads l ON DATE(l.created_at) = DATE(v.visit_date) 
        AND (l.type != 'visit' OR l.type IS NULL)
        AND (l.name != 'Посетитель' OR l.name IS NULL)
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
    GROUP BY DATE(v.visit_date)
    ORDER BY date
";

$stmt = $db->prepare($dailyQuery);
$stmt->execute([$dateFrom, $dateTo]);
$dailyStats = $stmt->fetchAll();

// ============================================
// 4. ТОП ИСТОЧНИКОВ (UTM)
// ============================================

$utmSourcesQuery = "
    SELECT 
        utm_source,
        utm_medium,
        utm_campaign,
        COUNT(*) as visits,
        COUNT(DISTINCT CASE WHEN lead_id IS NOT NULL THEN id END) as leads
    FROM visits 
    WHERE DATE(visit_date) BETWEEN ? AND ?
        AND (utm_source IS NOT NULL OR utm_medium IS NOT NULL)
    GROUP BY utm_source, utm_medium, utm_campaign
    ORDER BY visits DESC
    LIMIT 20
";

$stmt = $db->prepare($utmSourcesQuery);
$stmt->execute([$dateFrom, $dateTo]);
$utmStats = $stmt->fetchAll();

// Массивы для иконок и названий (с защитой от null)
$trafficIcons = [
    'paid_advertising' => '💸',
    'seo' => '🔍',
    'direct' => '🏠',
    'social' => '👥',
    'referral' => '🔗',
    'email' => '✉️',
    'telegram_bot' => '🤖',
    'yandex' => '🔶',
    'google' => '🔵',
    'yandex_direct' => '🔶',
    'google_ads' => '🔵',
    'facebook_ads' => '📘',
    'vk_ads' => '📱',
    'instagram' => '📸',
    'telegram' => '📱',
    '' => '🏠' // Пустая строка = прямой переход
];

$trafficLabels = [
    'paid_advertising' => 'Реклама',
    'seo' => 'SEO',
    'direct' => 'Прямой',
    'social' => 'Соцсети',
    'referral' => 'Рефералы',
    'email' => 'Email',
    'telegram_bot' => 'Telegram',
    'yandex' => 'Яндекс',
    'google' => 'Google',
    'yandex_direct' => 'Яндекс.Директ',
    'google_ads' => 'Google Ads',
    'facebook_ads' => 'Facebook',
    'vk_ads' => 'VK',
    'instagram' => 'Instagram',
    'telegram' => 'Telegram',
    '' => 'Прямой переход'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика трафика - ZLOCK</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        <?php include 'normalize.css'; ?>
        
        .statistics-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
            color: #041c2c;
        }
        
        .stat-label {
            font-size: 13px;
            color: #adb5bd;
        }
           
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    height: 400px; /* ФИКСИРОВАННАЯ ВЫСОТА */
    position: relative;
}

.chart-container canvas {
    max-height: 350px; /* Максимальная высота canvas */
    width: 100% !important;
    height: auto !important;
    max-width: 100%;
}

        .chart-container h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #495057;
        }
        
        .filters-form {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-form .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        .table-container h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            color: #495057;
        }
        
        .stats-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .stats-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .stats-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .stats-table tr:hover {
            background: #f8f9fa;
        }
        
        .conversion-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .conversion-high {
            background: #d4edda;
            color: #155724;
        }
        
        .conversion-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .conversion-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .traffic-icon {
            font-size: 18px;
            margin-right: 8px;
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header как в leads.php -->
    <div class='header-container'>
        <!-- Вставьте сюда header из leads.php -->
    </div>
    
    <div class='content-container'>
        <div class="statistics-container">
            <div class="content-header">
                <h1>📊 Статистика трафика</h1>
                <p>Анализ посещений и конверсий за период</p>
            </div>
            
            <!-- Фильтры -->
            <div class="filters-form">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="date_from">Дата с</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Дата по</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-line"></i>
                            Обновить
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Карточки с общей статистикой -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-eye"></i> Визиты</h3>
                    <div class="stat-value"><?php echo number_format($visitsStats['total_visits'] ?? 0); ?></div>
                    <div class="stat-label">уникальных: <?php echo number_format($visitsStats['unique_visitors'] ?? 0); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-file-signature"></i> Заявки</h3>
                    <div class="stat-value"><?php echo number_format($leadsStats['total_leads'] ?? 0); ?></div>
                    <div class="stat-label">
                        новых: <?php echo number_format($leadsStats['new_leads'] ?? 0); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-chart-line"></i> Конверсия</h3>
                    <div class="stat-value"><?php echo $conversionRate; ?>%</div>
                    <div class="stat-label">из визитов в заявки</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-users"></i> Эффективность</h3>
                    <div class="stat-value"><?php 
                        $visitsPerLead = $totalLeads > 0 ? round($totalVisits / $totalLeads, 1) : 0;
                        echo $visitsPerLead;
                    ?></div>
                    <div class="stat-label">визитов на 1 заявку</div>
                </div>
            </div>
            
            <!-- График по дням -->
            <div class="chart-container">
                <h2>Динамика посещений и заявок</h2>
                <canvas id="dailyChart" style="height: 300px;"></canvas>
            </div>
            
            <!-- Две колонки -->
            <div class="two-columns">
                <!-- Круговая диаграмма трафика -->
                <div class="chart-container">
                    <h2>Распределение визитов по источникам</h2>
                    <canvas id="trafficPieChart" style="height: 250px;"></canvas>
                </div>
                
                <!-- Круговая диаграмма заявок -->
                <div class="chart-container">
                    <h2>Распределение заявок по источникам</h2>
                    <canvas id="leadsPieChart" style="height: 250px;"></canvas>
                </div>
            </div>
            
            <!-- Таблица статистики по источникам -->
            <div class="table-container">
                <h2>Детальная статистика по источникам трафика</h2>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Источник</th>
                            <th>Визиты</th>
                            <th>Уникальные</th>
                            <th>Заявки</th>
                            <th>Конверсия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trafficData as $stat): 
                            // Безопасное получение значений - ИСПРАВЛЕНО!
                            $source = isset($stat['source']) ? $stat['source'] : '';
                            $visits = isset($stat['visits']) ? (int)$stat['visits'] : 0;
                            $uniqueVisits = isset($stat['unique_visits']) ? (int)$stat['unique_visits'] : 0;
                            $leads = isset($stat['leads']) ? (int)$stat['leads'] : 0;
                            $conversion = isset($stat['conversion']) ? (float)$stat['conversion'] : 0;
                            
                            // Получаем иконку и название с защитой от null
                            $icon = isset($trafficIcons[$source]) ? $trafficIcons[$source] : '❓';
                            $label = isset($trafficLabels[$source]) ? $trafficLabels[$source] : ($source ?: 'Прямой переход');
                            
                            // Определяем класс для конверсии
                            $convClass = 'conversion-low';
                            if ($conversion > 10) $convClass = 'conversion-high';
                            elseif ($conversion > 3) $convClass = 'conversion-medium';
                        ?>
                        <tr>
                            <td>
                                <span class="traffic-icon"><?php echo $icon; ?></span>
                                <?php echo htmlspecialchars($label); ?>
                            </td>
                            <td><strong><?php echo number_format($visits); ?></strong></td>
                            <td><?php echo number_format($uniqueVisits); ?></td>
                            <td><strong><?php echo number_format($leads); ?></strong></td>
                            <td>
                                <span class="conversion-badge <?php echo $convClass; ?>">
                                    <?php echo $conversion; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($trafficData)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #6c757d;">
                                Нет данных за выбранный период
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- UTM статистика -->
            <div class="table-container">
                <h2>Детализация по UTM-меткам</h2>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Источник</th>
                            <th>Тип</th>
                            <th>Кампания</th>
                            <th>Визиты</th>
                            <th>Заявки</th>
                            <th>Конверсия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utmStats as $utm): 
                            // Безопасное получение значений
                            $utmSource = isset($utm['utm_source']) ? $utm['utm_source'] : '-';
                            $utmMedium = isset($utm['utm_medium']) ? $utm['utm_medium'] : '-';
                            $utmCampaign = isset($utm['utm_campaign']) ? $utm['utm_campaign'] : '-';
                            $visits = isset($utm['visits']) ? (int)$utm['visits'] : 0;
                            $leads = isset($utm['leads']) ? (int)$utm['leads'] : 0;
                            $conv = $visits > 0 ? round(($leads / $visits) * 100, 2) : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($utmSource); ?></td>
                            <td><?php echo htmlspecialchars($utmMedium); ?></td>
                            <td><?php echo htmlspecialchars($utmCampaign); ?></td>
                            <td><?php echo number_format($visits); ?></td>
                            <td><?php echo number_format($leads); ?></td>
                            <td>
                                <span class="conversion-badge <?php echo $conv > 5 ? 'conversion-high' : 'conversion-low'; ?>">
                                    <?php echo $conv; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($utmStats)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #6c757d;">
                                Нет UTM-данных за выбранный период
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>




<script>
document.addEventListener('DOMContentLoaded', function() {
    // Уничтожаем существующие графики если они есть
    if (window.dailyChart instanceof Chart) {
        window.dailyChart.destroy();
    }
    if (window.trafficPieChart instanceof Chart) {
        window.trafficPieChart.destroy();
    }
    if (window.leadsPieChart instanceof Chart) {
        window.leadsPieChart.destroy();
    }
    
// График по дням
const dailyCanvas = document.getElementById('dailyChart');
if (dailyCanvas) {
    // Устанавливаем фиксированные размеры canvas
    dailyCanvas.style.width = '100%';
    dailyCanvas.style.height = '350px';
    dailyCanvas.width = dailyCanvas.offsetWidth;
    dailyCanvas.height = 350;
    
    const dailyCtx = dailyCanvas.getContext('2d');
    window.dailyChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                $labels = [];
                foreach ($dailyStats as $day) {
                    $labels[] = isset($day['date']) ? date("d.m", strtotime($day['date'])) : '';
                }
                echo "'" . implode("','", $labels) . "'";
                ?>
            ],
            datasets: [
                {
                    label: 'Визиты',
                    data: [
                        <?php 
                        $visits = [];
                        foreach ($dailyStats as $day) {
                            $visits[] = isset($day['visits']) ? (int)$day['visits'] : 0;
                        }
                        echo implode(',', $visits);
                        ?>
                    ],
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5
                },
                {
                    label: 'Заявки',
                    data: [
                        <?php 
                        $leads = [];
                        foreach ($dailyStats as $day) {
                            $leads[] = isset($day['leads']) ? (int)$day['leads'] : 0;
                        }
                        echo implode(',', $leads);
                        ?>
                    ],
                    borderColor: '#FF6384',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // ВАЖНО: отключаем сохранение соотношения сторон
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            },
            animation: {
                duration: 0 // Отключаем анимацию для стабильности
            }
        }
    });
}
    
    // Круговая диаграмма - Визиты
    const trafficCanvas = document.getElementById('trafficPieChart');
    if (trafficCanvas) {
        const trafficCtx = trafficCanvas.getContext('2d');
        
        <?php 
        // Подготавливаем данные для круговой диаграммы визитов
        $pieLabels = [];
        $pieData = [];
        $pieColors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#8AC926', '#1982C4'];
        
        foreach ($trafficData as $index => $stat) {
            $source = isset($stat['source']) ? $stat['source'] : '';
            $label = isset($trafficLabels[$source]) ? $trafficLabels[$source] : ($source ?: 'Прямой переход');
            $visits = isset($stat['visits']) ? (int)$stat['visits'] : 0;
            
            if ($visits > 0) {
                $pieLabels[] = $label;
                $pieData[] = $visits;
            }
        }
        ?>
        
        window.trafficPieChart = new Chart(trafficCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($pieLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($pieData); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($pieColors, 0, count($pieData))); ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }
    
    // Круговая диаграмма - Заявки
    const leadsCanvas = document.getElementById('leadsPieChart');
    if (leadsCanvas) {
        const leadsCtx = leadsCanvas.getContext('2d');
        
        <?php 
        // Подготавливаем данные для круговой диаграммы заявок
        $leadsLabels = [];
        $leadsData = [];
        $leadsColors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
        
        foreach ($trafficData as $stat) {
            $source = isset($stat['source']) ? $stat['source'] : '';
            $label = isset($trafficLabels[$source]) ? $trafficLabels[$source] : ($source ?: 'Прямой переход');
            $leads = isset($stat['leads']) ? (int)$stat['leads'] : 0;
            
            if ($leads > 0) {
                $leadsLabels[] = $label;
                $leadsData[] = $leads;
            }
        }
        ?>
        
        if (<?php echo count($leadsData); ?> > 0) {
            window.leadsPieChart = new Chart(leadsCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($leadsLabels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [{
                        data: <?php echo json_encode($leadsData); ?>,
                        backgroundColor: <?php echo json_encode(array_slice($leadsColors, 0, count($leadsData))); ?>,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        } else {
            leadsCanvas.parentNode.innerHTML += '<p style="text-align: center; color: #6c757d; padding: 20px;">Нет заявок за период</p>';
        }
    }
});
</script>


</body>
</html>