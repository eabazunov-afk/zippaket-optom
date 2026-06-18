<?php
/**
 * UTM трекер для отслеживания посещений
 */

class UTMTracker {
    
    /**
     * Инициализация трекинга
     */
    public static function init() {
        // Проверяем, не бот ли это
        if (self::isBot()) {
            return;
        }
        
        // Проверяем, не был ли визит уже записан в эту сессию
        if (isset($_SESSION['visit_tracked'])) {
            return;
        }
        
        // Собираем данные о посещении
        $visitData = self::collectVisitData();
        
        // Сохраняем визит в БД
        $visitId = self::saveVisit($visitData);
        
        if ($visitId) {
            $_SESSION['visit_tracked'] = true;
            $_SESSION['first_visit_time'] = date('Y-m-d H:i:s');
            $_SESSION['visit_data'] = $visitData;
            $_SESSION['visit_id'] = $visitId;
            
            // Логируем успешное сохранение
            error_log("Visit tracked: ID $visitId, Source: " . ($visitData['traffic_source'] ?? 'unknown'));
        }
    }
    
    /**
     * Сбор данных о посещении
     */
    private static function collectVisitData() {
        $data = [
            'type' => 'visit',
            'source' => 'website',
            'status' => 'new',
            'utm_source' => $_GET['utm_source'] ?? null,
            'utm_medium' => $_GET['utm_medium'] ?? null,
            'utm_campaign' => $_GET['utm_campaign'] ?? null,
            'utm_term' => $_GET['utm_term'] ?? null,
            'utm_content' => $_GET['utm_content'] ?? null,
            'gclid' => $_GET['gclid'] ?? null,
            'yclid' => $_GET['yclid'] ?? null,
            'fbclid' => $_GET['fbclid'] ?? null,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'landing_page' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'first_visit' => date('Y-m-d H:i:s')
        ];
        
        // Определяем трафик
        $data['traffic_source'] = self::detectTrafficSource($data);
        $data['traffic_type'] = self::getTrafficType($data);
        
        // Определяем устройство
        $data['device_type'] = self::detectDevice();
        $data['is_bot'] = self::isBot() ? 1 : 0;
        
        // Определяем поисковую систему и запрос
        $searchData = self::extractSearchData($data['referrer']);
        $data['search_engine'] = $searchData['engine'];
        $data['search_query'] = $searchData['query'];
        
        return $data;
    }
    
    /**
     * Определение типа трафика
     */
    private static function getTrafficType($data) {
        // 1. Рекламный трафик (платный)
        if (!empty($data['utm_source']) && in_array($data['utm_medium'] ?? '', ['cpc', 'ppc', 'cpa'])) {
            return 'paid_advertising';
        }
        
        // 2. Клики по ID
        if (!empty($data['gclid']) || !empty($data['yclid']) || !empty($data['fbclid'])) {
            return 'paid_advertising';
        }
        
        // 3. Органический поиск (SEO)
        if ($data['traffic_source'] === 'organic_google' || 
            $data['traffic_source'] === 'organic_yandex' ||
            $data['traffic_source'] === 'organic_bing') {
            return 'seo';
        }
        
        // 4. Прямые переходы
        if ($data['traffic_source'] === 'direct') {
            return 'direct';
        }
        
        // 5. Реферальный трафик
        if (strpos($data['traffic_source'], 'referral_') === 0) {
            return 'referral';
        }
        
        // 6. Социальные сети
        if (in_array($data['traffic_source'], ['facebook', 'vkontakte', 'instagram', 'telegram'])) {
            return 'social';
        }
        
        // 7. Email
        if (strpos($data['traffic_source'], 'email') !== false) {
            return 'email';
        }
        
        return 'unknown';
    }
    
    /**
     * Определение источника трафика
     */
    private static function detectTrafficSource($data) {
        // 1. Прямой переход
        if (empty($data['referrer']) && empty($data['utm_source']) && 
            empty($data['gclid']) && empty($data['yclid']) && empty($data['fbclid'])) {
            return 'direct';
        }
        
        // 2. UTM метки
        if (!empty($data['utm_source'])) {
            return $data['utm_source'];
        }
        
        // 3. Google Ads
        if (!empty($data['gclid'])) {
            return 'google_ads';
        }
        
        // 4. Яндекс.Директ
        if (!empty($data['yclid'])) {
            return 'yandex_direct';
        }
        
        // 5. Facebook Ads
        if (!empty($data['fbclid'])) {
            return 'facebook_ads';
        }
        
        // 6. Поисковые системы
        $referrer = strtolower($data['referrer']);
        
        if (strpos($referrer, 'google.com/search') !== false) {
            return 'organic_google';
        }
        if (strpos($referrer, 'yandex.ru/search') !== false) {
            return 'organic_yandex';
        }
        if (strpos($referrer, 'bing.com/search') !== false) {
            return 'organic_bing';
        }
        if (strpos($referrer, 'mail.ru/search') !== false) {
            return 'organic_mail';
        }
        
        // 7. Социальные сети
        if (strpos($referrer, 'facebook.com') !== false) {
            return 'facebook';
        }
        if (strpos($referrer, 'vk.com') !== false) {
            return 'vkontakte';
        }
        if (strpos($referrer, 'instagram.com') !== false) {
            return 'instagram';
        }
        if (strpos($referrer, 'telegram.org') !== false) {
            return 'telegram';
        }
        
        // 8. Парсим домен реферера
        if (!empty($data['referrer'])) {
            $domain = parse_url($data['referrer'], PHP_URL_HOST);
            if ($domain) {
                return 'referral_' . $domain;
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Извлечение данных поиска из реферера
     */
    private static function extractSearchData($referrer) {
        $result = ['engine' => null, 'query' => null];
        
        if (empty($referrer)) {
            return $result;
        }
        
        $referrer = strtolower($referrer);
        
        // Google
        if (strpos($referrer, 'google.com/search') !== false) {
            $result['engine'] = 'google';
            parse_str(parse_url($referrer, PHP_URL_QUERY), $params);
            $result['query'] = $params['q'] ?? null;
        }
        // Яндекс
        elseif (strpos($referrer, 'yandex.ru/search') !== false) {
            $result['engine'] = 'yandex';
            parse_str(parse_url($referrer, PHP_URL_QUERY), $params);
            $result['query'] = $params['text'] ?? null;
        }
        // Mail.ru
        elseif (strpos($referrer, 'mail.ru/search') !== false) {
            $result['engine'] = 'mail';
            parse_str(parse_url($referrer, PHP_URL_QUERY), $params);
            $result['query'] = $params['q'] ?? null;
        }
        
        return $result;
    }
    
    /**
     * Определение устройства
     */
    private static function detectDevice() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($userAgent)) {
            return 'unknown';
        }
        
        // Мобильные устройства
        $mobileAgents = [
            'mobile', 'android', 'iphone', 'ipod', 'ipad', 
            'blackberry', 'webos', 'windows phone'
        ];
        
        foreach ($mobileAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return 'mobile';
            }
        }
        
        // Планшеты
        if (stripos($userAgent, 'tablet') !== false) {
            return 'tablet';
        }
        
        // Десктоп по умолчанию
        return 'desktop';
    }
    
    /**
     * Проверка на бота
     */
    private static function isBot() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (empty($userAgent)) {
            return true;
        }
        
        $bots = [
            'bot', 'crawler', 'spider', 'scraper', 'checker',
            'googlebot', 'yandexbot', 'bingbot', 'facebookbot',
            'slurp', 'duckduckbot', 'baiduspider', 'sogou',
            'exabot', 'facebot', 'ia_archiver'
        ];
        
        foreach ($bots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Сохранение визита в БД
     */
   private static function saveVisit($data) {
    $db = getDbConnection();
    
    try {
        // Устанавливаем кодировку
        $db->exec("SET NAMES utf8mb4");
        
        $stmt = $db->prepare("
            INSERT INTO visits 
            (ip_address, user_agent, referrer, landing_page, 
             utm_source, utm_medium, utm_campaign, utm_term, utm_content,
             gclid, yclid, fbclid, device_type, is_bot, 
             traffic_source, search_engine, search_query, session_id)
            VALUES 
            (:ip, :ua, :referrer, :landing,
             :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content,
             :gclid, :yclid, :fbclid, :device, :is_bot,
             :traffic_source, :search_engine, :search_query, :session_id)
        ");
        
        $result = $stmt->execute([
            ':ip' => $data['ip_address'],
            ':ua' => $data['user_agent'],
            ':referrer' => $data['referrer'],
            ':landing' => $data['landing_page'],
            ':utm_source' => $data['utm_source'],
            ':utm_medium' => $data['utm_medium'],
            ':utm_campaign' => $data['utm_campaign'],
            ':utm_term' => $data['utm_term'],
            ':utm_content' => $data['utm_content'],
            ':gclid' => $data['gclid'],
            ':yclid' => $data['yclid'],
            ':fbclid' => $data['fbclid'],
            ':device' => $data['device_type'],
            ':is_bot' => $data['is_bot'],
            ':traffic_source' => $data['traffic_source'],
            ':search_engine' => $data['search_engine'],
            ':search_query' => $data['search_query'],
            ':session_id' => session_id()
        ]);
        
        if ($result) {
            $visitId = $db->lastInsertId();
            error_log("✅ Visit saved to visits table with ID: $visitId");
            return $visitId;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("❌ Error saving visit: " . $e->getMessage());
        return false;
    }
}
    
   /**
 * Связь заявки с визитом
 */
public static function linkLeadToVisit($leadId) {
    if (isset($_SESSION['visit_id']) && isset($_SESSION['visit_data'])) {
        $db = getDbConnection();
        
        try {
            // Получаем текущие параметры заявки
            $stmt = $db->prepare("SELECT parameters FROM leads WHERE id = ?");
            $stmt->execute([$leadId]);
            $lead = $stmt->fetch();
            
            $currentParams = [];
            if (!empty($lead['parameters'])) {
                try {
                    $currentParams = json_decode($lead['parameters'], true);
                    if (!is_array($currentParams)) {
                        $currentParams = [];
                    }
                } catch (Exception $e) {
                    $currentParams = [];
                }
            }
            
            // Добавляем данные визита
            $visitData = $_SESSION['visit_data'];
            $mergedParams = array_merge($currentParams, [
                'visit_id' => $_SESSION['visit_id'],
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
                'first_visit' => $visitData['first_visit'] ?? null,
                'is_bot' => $visitData['is_bot'] ?? 0
            ]);
            
            // Обновляем заявку
            $updateStmt = $db->prepare("
                UPDATE leads 
                SET parameters = ?
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                json_encode($mergedParams, JSON_UNESCAPED_UNICODE),
                $leadId
            ]);
            
            error_log("UTM Tracker: Заявка #$leadId связана с визитом #" . $_SESSION['visit_id']);
            error_log("UTM Tracker: Добавлены параметры: " . json_encode($mergedParams));
            
            return true;
            
        } catch (Exception $e) {
            error_log("UTM Tracker LINK ERROR: " . $e->getMessage());
            return false;
        }
    }
    
    error_log("UTM Tracker: Нет данных визита для связи с заявкой #$leadId");
    return false;
}
        
       
    }

?>