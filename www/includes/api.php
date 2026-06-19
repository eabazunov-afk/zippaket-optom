<?php
/**
 * API для обработки запросов
 */
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
header('Content-Type: application/json; charset=utf-8');

// Включаем максимальное логирование
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/c103264/zippaket-optom.ru/www/includes/debug.log');

// Логируем начало запроса
error_log("==========================================");
error_log("API REQUEST START: " . date('Y-m-d H:i:s'));
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("REQUEST URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
error_log("CONTENT TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

require_once 'config.php';
require_once 'calculator.php';

// Подключаем UTM трекер если существует
if (file_exists(__DIR__ . '/utm_tracker.php')) {
    require_once __DIR__ . '/utm_tracker.php';
    error_log("UTM Tracker: подключен");
} else {
    error_log("UTM Tracker: файл не найден");
}

header('Content-Type: application/json');
// CORS '*' убран намеренно: лид-эндпоинт принимает запросы только с нашего домена
// (браузер блокирует cross-origin JSON-fetch без Allow-Origin) — меньше спама/CSRF.
require_once __DIR__ . '/recaptcha.php';

// Упрощенный обработчик ошибок
function handleError($message, $code = 500) {
    error_log("API ERROR: " . $message);
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

try {
    // Получаем действие из запроса
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
    
    if (empty($action)) {
        handleError('Не указано действие', 400);
    }
    
    error_log("API Action: $action");
    
    // Получаем входные данные
    $input = file_get_contents('php://input');
    error_log("Raw input (first 1000 chars): " . substr($input, 0, 1000));
    
    // Обработка разных действий
    switch ($action) {
        case 'calculate':
            handleCalculate();
            break;
            
        case 'save_calculation':
            handleSaveCalculation();
            break;
            
        case 'request_offer':
            handleRequestOffer();
            break;
            
        case 'submit_offer_cart':
            handleSubmitOfferCart();
            break;
            
        case 'save_lead':
            handleSaveLead();
            break;
            
        case 'save_cart_items':
            handleSaveCartItems();
            break;
            
        default:
            handleError('Неизвестное действие: ' . $action, 400);
    }
} catch (Exception $e) {
    handleError('Исключение: ' . $e->getMessage() . ' в файле ' . $e->getFile() . ' на строке ' . $e->getLine());
}

/**
 * Обработка запроса на расчёт стоимости
 */
function handleCalculate() {
    $input = file_get_contents('php://input');
    error_log("Calculate input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("Calculate JSON error: " . json_last_error_msg());
        throw new Exception('Неверный формат данных JSON');
    }
    
    $requiredFields = ['type', 'width', 'height', 'thickness', 'quantity'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Отсутствует обязательное поле: $field");
        }
    }
    
    $calculator = new Calculator();
    
    $result = $calculator->calculatePrice(
        $data['type'],
        $data['width'],
        $data['height'],
        $data['thickness'],
        isset($data['material']) ? $data['material'] : null,
        $data['quantity']
    );
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    exit;
}

/**
 * Обработка сохранения расчёта
 */
function handleSaveCalculation() {
    $input = file_get_contents('php://input');
    error_log("SaveCalculation input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("SaveCalculation JSON error: " . json_last_error_msg());
        throw new Exception('Неверный формат данных JSON');
    }
    
    // Сохраняем в БД
    $calculationId = 'CALC-' . time() . '-' . bin2hex(random_bytes(3));
    $data['calculation_id'] = $calculationId;
    
    $saveResult = saveCalculationToDB($data); // Изменено имя функции!
    
    if ($saveResult) {
        echo json_encode([
            'success' => true,
            'calculation_id' => $calculationId,
            'message' => 'Расчёт сохранен успешно'
        ]);
    } else {
        throw new Exception('Ошибка при сохранении расчета');
    }
    
    exit;
}

/**
 * Обработка запроса коммерческого предложения
 */
function handleRequestOffer() {
    $input = file_get_contents('php://input');
    error_log("RequestOffer input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("RequestOffer JSON error: " . json_last_error_msg());
        throw new Exception('Неверный формат данных JSON');
    }
    
    if (empty($data['name']) || empty($data['phone'])) {
        throw new Exception('Заполните обязательные поля: имя и телефон');
    }
    
    // Сохраняем в БД как заявку
    $leadData = [
        'name' => $data['name'],
        'phone' => $data['phone'],
        'email' => isset($data['email']) ? $data['email'] : '',
        'type' => 'offer_request',
        'message' => isset($data['message']) ? $data['message'] : '',
        'source' => 'calculator',
        'parameters' => isset($data['calculation']) ? json_encode($data['calculation']) : '[]'
    ];
    
    $leadId = saveLeadToDB($leadData); // Изменено имя функции!
    
    if ($leadId) {
        // Добавляем lead_id в данные для AmoCRM
        $leadData['lead_id'] = $leadId;
        
        // Связываем заявку с визитом если UTM трекер подключен
        if (class_exists('UTMTracker')) {
            UTMTracker::linkLeadToVisit($leadId);
        }
        
        // Отправляем в AmoCRM
        $amoCRMResult = false;
        if (function_exists('sendToAmoCRM')) {
            $amoCRMResult = sendToAmoCRM($leadData);
        }
        
        // Отправляем email уведомление
        if (defined('ADMIN_EMAIL') && function_exists('sendEmail')) {
            $emailSubject = 'Новая заявка на КП - ' . SITE_NAME;
            $emailMessage = "
                <h2>Новая заявка на коммерческое предложение</h2>
                <p><strong>Имя:</strong> {$data['name']}</p>
                <p><strong>Телефон:</strong> {$data['phone']}</p>
                <p><strong>Email:</strong> " . (isset($data['email']) ? $data['email'] : 'не указан') . "</p>
                <p><strong>Сообщение:</strong> " . (isset($data['message']) ? $data['message'] : 'нет') . "</p>
                <p><strong>AmoCRM ID:</strong> " . ($amoCRMResult ?: 'не отправлено') . "</p>
                <p><strong>Дата:</strong> " . date('d.m.Y H:i:s') . "</p>
            ";
            
            sendEmail(ADMIN_EMAIL, $emailSubject, $emailMessage);
        }
        
        echo json_encode([
            'success' => true,
            'lead_id' => $leadId,
            'amo_crm_id' => $amoCRMResult,
            'message' => 'Запрос отправлен успешно'
        ]);
    } else {
        throw new Exception('Ошибка при сохранении заявки');
    }
    
    exit;
}

/**
 * Обработка отправки корзины заявок
 */
function handleSubmitOfferCart() {
    $input = file_get_contents('php://input');
    error_log("SubmitOfferCart input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("SubmitOfferCart JSON error: " . json_last_error_msg());
        throw new Exception('Неверный формат данных JSON');
    }
    
    if (empty($data['items']) || !is_array($data['items'])) {
        throw new Exception('Корзина пуста');
    }
    
    // Сохраняем корзину в БД
    $cartData = [
        'cart_id' => 'CART-' . time() . '-' . bin2hex(random_bytes(3)),
        'items' => $data['items'],
        'total_items' => isset($data['total_items']) ? $data['total_items'] : count($data['items'])
    ];
    
    $cartId = saveOfferCartToDB($cartData); // Изменено имя функции!
    
    echo json_encode([
        'success' => true,
        'cart_id' => $cartId,
        'message' => 'Заявка отправлена успешно',
        'items_count' => count($data['items']),
        'total_items' => isset($data['total_items']) ? $data['total_items'] : 0
    ]);
    exit;
}

/**
 * Обработка сохранения заявки
 */
function handleSaveLead() {
    $input = file_get_contents('php://input');
    error_log("SaveLead input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("SaveLead JSON error: " . json_last_error_msg());
        throw new Exception('Неверный формат данных JSON');
    }
    
    if (empty($data['name']) || empty($data['phone'])) {
        throw new Exception('Заполните обязательные поля: имя и телефон');
    }
    
    // Простая валидация телефона
    if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $data['phone'])) {
        throw new Exception('Укажите корректный номер телефона');
    }
    
    // Валидация email если указан
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Укажите корректный email адрес');
    }

    // Анти-бот: проверка reCAPTCHA (токен приходит из формы, см. js/script.js)
    if (!recaptcha_verify($data['recaptcha_token'] ?? '', '')) {
        throw new Exception('Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.');
    }
    
    // Собираем параметры
    $parameters = [];
    if (isset($data['parameters'])) {
        if (is_string($data['parameters'])) {
            $params = json_decode($data['parameters'], true);
            if ($params && is_array($params)) {
                $parameters = $params;
            }
        } else {
            $parameters = $data['parameters'];
        }
    }
    
    // Добавляем комментарий если есть
if (!empty($data['comment'])) {
    $parameters['comment'] = $data['comment'];
} elseif (!empty($data['message'])) {
    $parameters['comment'] = $data['message'];
}
    
    // Добавляем UTM данные к параметрам перед сохранением
    if (class_exists('UTMTracker')) {
        // Начинаем сессию если не начата
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Если есть данные визита в сессии
        if (isset($_SESSION['visit_data'])) {
            $visitData = $_SESSION['visit_data'];
            
            // Добавляем UTM данные
            $parameters = array_merge($parameters, [
                'traffic_type' => $visitData['traffic_type'] ?? 'unknown',
                'traffic_source' => $visitData['traffic_source'] ?? 'unknown',
                'utm_source' => $visitData['utm_source'] ?? null,
                'utm_medium' => $visitData['utm_medium'] ?? null,
                'utm_campaign' => $visitData['utm_campaign'] ?? null,
                'utm_term' => $visitData['utm_term'] ?? null,
                'utm_content' => $visitData['utm_content'] ?? null,
                'gclid' => $visitData['gclid'] ?? null,
                'yclid' => $visitData['yclid'] ?? null,
                'fbclid' => $visitData['fbclid'] ?? null,
                'search_query' => $visitData['search_query'] ?? null,
                'device' => $visitData['device_type'] ?? null,
                'is_bot' => $visitData['is_bot'] ?? 0
            ]);
            
            // Добавляем visit_id если есть
            if (isset($_SESSION['visit_id'])) {
                $parameters['visit_id'] = $_SESSION['visit_id'];
            }
            
            // Добавляем first_visit
            if (isset($visitData['first_visit'])) {
                $parameters['first_visit'] = $visitData['first_visit'];
            }
            
            error_log("API: Добавлены UTM параметры к заявке");
        } else {
            error_log("API: Нет данных визита в сессии для заявки");
        }
    } else {
        error_log("API: Класс UTMTracker не найден");
    }
    
    // Подготавливаем данные для сохранения в БД
    $leadData = [
        'name' => trim($data['name']),
        'phone' => trim($data['phone']),
        'email' => isset($data['email']) ? trim($data['email']) : '',
        'type' => isset($data['type']) ? $data['type'] : 'contact_form',
        'source' => isset($data['source']) ? $data['source'] : 'website',
        'message' => isset($data['message']) ? $data['message'] : (isset($data['comment']) ? $data['comment'] : ''),
        'parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
        'status' => 'new',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    error_log("Lead data prepared for DB: " . print_r($leadData, true));
    
    // Сохраняем в БД
    $leadId = saveLeadToDB($leadData); // Изменено имя функции!
    
    if (!$leadId) {
        error_log("Failed to save lead to database");
        throw new Exception('Ошибка при сохранении заявки в базу данных');
    }
    
    error_log("Lead saved to DB with ID: $leadId");
    
    // Отправляем в AmoCRM - ИСПРАВЛЕННЫЙ КОД
    $amoCRMResult = false;
    if (file_exists(__DIR__ . '/amocrm.php')) {
        require_once __DIR__ . '/amocrm.php';
        
        if (function_exists('sendToAmoCRM')) {
            // Подготавливаем данные для AmoCRM в ПРАВИЛЬНОМ ФОРМАТЕ
            $amoData = [
                'name' => $leadData['name'],
                'phone' => $leadData['phone'],
                'email' => $leadData['email'],
                'type' => $leadData['type'],
                'source' => $leadData['source'],
                'message' => $leadData['message'],
                'parameters' => $parameters, // Уже массив с UTM данными
                'lead_id' => $leadId
            ];
            
            
            error_log("Sending to AmoCRM: " . print_r($amoData, true));
            
            $amoCRMResult = sendToAmoCRM($amoData);
            
            if ($amoCRMResult) {
                error_log("AmoCRM response: Success, ID: $amoCRMResult");
                // Обновляем запись в базе данных с ID сделки из AmoCRM
                updateLeadWithAmoCRMId($leadId, $amoCRMResult);
            } else {
                error_log("AmoCRM response: Failed");
            }
        } else {
            error_log("Function sendToAmoCRM not found");
        }
    } else {
        error_log("amocrm.php file not found");
    }
    
    // Отправляем email уведомление если настроено
    $emailSent = false;
    if (defined('ADMIN_EMAIL') && defined('SITE_NAME') && function_exists('sendEmail')) {
        $emailSubject = 'Новая заявка с сайта - ' . SITE_NAME;
        $emailMessage = "
            <h2>Новая заявка с сайта</h2>
            <p><strong>Тип заявки:</strong> " . htmlspecialchars($leadData['type']) . "</p>
            <p><strong>Имя:</strong> " . htmlspecialchars($leadData['name']) . "</p>
            <p><strong>Телефон:</strong> " . htmlspecialchars($leadData['phone']) . "</p>
            <p><strong>Email:</strong> " . ($leadData['email'] ? htmlspecialchars($leadData['email']) : 'не указан') . "</p>
            <p><strong>Сообщение:</strong> " . ($leadData['message'] ? htmlspecialchars($leadData['message']) : 'нет') . "</p>
            <p><strong>AmoCRM ID:</strong> " . ($amoCRMResult ? htmlspecialchars($amoCRMResult) : 'не отправлено') . "</p>
            <p><strong>Дата:</strong> " . date('d.m.Y H:i:s') . "</p>
        ";
        
        try {
            sendEmail(ADMIN_EMAIL, $emailSubject, $emailMessage);
            $emailSent = true;
            error_log("Email notification sent successfully");
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
        }
    }
    
    // Успешный ответ
    echo json_encode([
        'success' => true,
        'lead_id' => $leadId,
        'amo_crm_id' => $amoCRMResult,
        'email_sent' => $emailSent,
        'message' => 'Заявка успешно отправлена'
    ]);
    
    error_log("handleSaveLead completed successfully");
    exit;
}

/**
 * Обработка сохранения товаров в корзине
 */
function handleSaveCartItems() {
    $input = file_get_contents('php://input');
    error_log("SaveCartItems input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("SaveCartItems JSON error: " . json_last_error_msg());
        throw new Exception('Неверный формат данных JSON');
    }
    
    // Сохраняем в localStorage на клиенте, а здесь можно логировать
    $itemsCount = isset($data['items']) ? count($data['items']) : 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Корзина обновлена',
        'items_count' => $itemsCount
    ]);
    exit;
}



function saveLeadToDB($data) {
    try {
        error_log("=== saveLeadToDB START ===");
        
        if (!function_exists('getDbConnection')) {
            error_log("ERROR: getDbConnection function not found");
            return false;
        }
        
        $db = getDbConnection();
        
        // Устанавливаем кодировку соединения
        $db->exec("SET NAMES utf8mb4");
        
        // Получаем комментарий из разных источников
        $comment = '';
        if (!empty($data['message'])) {
            $comment = $data['message'];
        } elseif (!empty($data['comment'])) {
            $comment = $data['comment'];
        }
        
        // Получаем данные окружения
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $landing_page = $_SERVER['REQUEST_URI'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Обрабатываем parameters
        $parameters = $data['parameters'] ?? [];
        if (is_string($parameters)) {
            $decoded = json_decode($parameters, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parameters = $decoded;
            }
        }
        
        // Добавляем UTM данные из сессии если есть
        if (isset($_SESSION['visit_data'])) {
            $visitData = $_SESSION['visit_data'];
            $parameters = array_merge($parameters, [
                'traffic_type' => $visitData['traffic_type'] ?? 'unknown',
                'traffic_source' => $visitData['traffic_source'] ?? 'unknown',
                'utm_source' => $visitData['utm_source'] ?? null,
                'utm_medium' => $visitData['utm_medium'] ?? null,
                'utm_campaign' => $visitData['utm_campaign'] ?? null,
                'utm_term' => $visitData['utm_term'] ?? null,
                'utm_content' => $visitData['utm_content'] ?? null,
                'gclid' => $visitData['gclid'] ?? null,
                'yclid' => $visitData['yclid'] ?? null,
                'fbclid' => $visitData['fbclid'] ?? null,
                'device' => $visitData['device_type'] ?? null,
                'visit_id' => $_SESSION['visit_id'] ?? null
            ]);
        }
        
        // Конвертируем parameters обратно в JSON
        $parametersJson = json_encode($parameters, JSON_UNESCAPED_UNICODE);
        
        // ПРАВИЛЬНЫЙ INSERT - все поля соответствуют структуре таблицы
        $sql = "INSERT INTO leads (
            name, phone, email, type, parameters, comment, status, source,
            referrer, landing_page, ip_address, user_agent, created_at
        ) VALUES (
            :name, :phone, :email, :type, :parameters, :comment, :status, :source,
            :referrer, :landing_page, :ip_address, :user_agent, NOW()
        )";
        
        $stmt = $db->prepare($sql);
        
          $result = $stmt->execute([
            ':name' => trim($data['name'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':email' => trim($data['email'] ?? ''),
            ':type' => $data['type'] ?? 'contact_form',
            ':parameters' => $parametersJson,
            ':comment' => $comment,
            ':status' => 'new',
            ':source' => $data['source'] ?? 'website',
            ':referrer' => $referrer,
            ':landing_page' => $landing_page,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
        
        if ($result) {
            $leadId = $db->lastInsertId();
            error_log("✅ Lead saved to DB with ID: $leadId");
            
            // Связываем с визитом - ИСПРАВЛЕНО
            if (isset($_SESSION['visit_id']) && class_exists('UTMTracker')) {
                UTMTracker::linkLeadToVisit($leadId);
                error_log("Linked lead #$leadId to visit #{$_SESSION['visit_id']}");
            }
            
            return $leadId;
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("❌ DB insert failed: " . print_r($errorInfo, true));
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ saveLeadToDB Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Обновление записи с ID AmoCRM
 */
function updateLeadWithAmoCRMId($leadId, $amoCRMId) {
    try {
        if (!function_exists('getDbConnection')) {
            return false;
        }
        
        $db = getDbConnection();
        
        // Используем правильные названия полей из вашей таблицы
        $stmt = $db->prepare("UPDATE leads SET external_id = ?, amocrm_synced_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$amoCRMId, $leadId]);
        
        if ($result) {
            error_log("✅ Lead #$leadId updated with AmoCRM ID: $amoCRMId");
            return true;
        } else {
            error_log("❌ Failed to update lead with AmoCRM ID");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ updateLeadWithAmoCRMId error: " . $e->getMessage());
        return false;
    }
}

/**
 * Сохранение расчета в БД
 */
function saveCalculationToDB($data) { // Изменено имя функции!
    try {
        error_log("saveCalculationToDB function called");
        // Простая симуляция сохранения
        $calculationId = $data['calculation_id'] ?? 'CALC-' . time();
        error_log("Calculation saved with ID: $calculationId");
        return $calculationId;
    } catch (Exception $e) {
        error_log("saveCalculationToDB error: " . $e->getMessage());
        return false;
    }
}

/**
 * Сохранение корзины в БД
 */
function saveOfferCartToDB($data) { // Изменено имя функции!
    try {
        error_log("saveOfferCartToDB function called");
        // Простая симуляция сохранения
        $cartId = $data['cart_id'] ?? 'CART-' . time();
        error_log("Cart saved with ID: $cartId");
        return $cartId;
    } catch (Exception $e) {
        error_log("saveOfferCartToDB error: " . $e->getMessage());
        return false;
    }
}
?>