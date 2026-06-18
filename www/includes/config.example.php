<?php
// ШАБЛОН конфигурации. Скопировать в config.php и заполнить реальными значениями.
// config.php НЕ коммитится (см. .gitignore). Здесь только плейсхолдеры вместо секретов;
// вся логика (функции, сессия) идентична config.php.

// Подключение функций
require_once dirname(__FILE__) . '/functions.php';

define('RECAPTCHA_SITE_KEY', 'ВАШ_RECAPTCHA_SITE_KEY');
define('RECAPTCHA_SECRET_KEY', 'ВАШ_RECAPTCHA_SECRET_KEY');

// Настройки времени
date_default_timezone_set('Europe/Moscow');

// Константы путей
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

define('AMOCRM_DOMAIN', 'ваш-аккаунт.amocrm.ru'); // Ваш домен AmoCRM
define('AMOCRM_ACCESS_TOKEN', 'ВАШ_AMOCRM_ACCESS_TOKEN'); // Long-lived токен
define('AMOCRM_CLIENT_ID', 'ВАШ_AMOCRM_CLIENT_ID'); // ID интеграции
define('AMOCRM_CLIENT_SECRET', 'ВАШ_AMOCRM_CLIENT_SECRET'); // Секретный ключ
define('AMOCRM_REDIRECT_URI', 'https://zippaket-optom.ru/includes/amocrm.php'); // URI для колбэка

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'имя_базы');
define('DB_USER', 'пользователь_базы');
define('DB_PASS', 'ПАРОЛЬ_БАЗЫ');

// Настройки сайта
define('SITE_NAME', 'ZIP-Пакеты оптом по всей России');
define('SITE_URL', 'https://zippaket-optom.ru/');
define('ADMIN_EMAIL', 'info@zip-site.ru');
define('SUPPORT_PHONE', '8 (800) 123-45-67');

// Платёжный шлюз (ЮKassa) — План 4.
// shopId+secretKey берутся из ЛК ЮKassa (тестовый магазин — для разработки).
// Webhook настраивается в ЛК на URL: https://<домен>/api/payment_callback.php (событие payment.succeeded).
define('YOOKASSA_SHOP_ID', 'ВАШ_YOOKASSA_SHOP_ID');
define('YOOKASSA_SECRET_KEY', 'ВАШ_YOOKASSA_SECRET_KEY');
define('YOOKASSA_API_URL', 'https://api.yookassa.ru/v3');
// Код НДС для чека 54-ФЗ: 1=без НДС, 2=0%, 3=10%, 4=20%, 5=10/110, 6=20/120
define('YOOKASSA_VAT_CODE', 1);

// Настройки безопасности
define('SESSION_NAME', 'ZIP_SESSION');
define('SESSION_LIFETIME', 86400);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Функция для безопасного подключения к БД
function getDbConnection() {
    static $connection = null;

    if ($connection === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $connection = new PDO($dsn, DB_USER, DB_PASS);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // Устанавливаем кодировку
            $connection->exec("SET NAMES utf8mb4");

        } catch (PDOException $e) {
            error_log("Ошибка подключения к БД: " . $e->getMessage());

            if (ini_get('display_errors')) {
                die("Ошибка подключения к базе данных: " . htmlspecialchars($e->getMessage()));
            } else {
                die("Ошибка подключения к базе данных. Пожалуйста, попробуйте позже.");
            }
        }
    }

    return $connection;
}

// Функция для безопасного вывода данных
function safeOutput($data) {
    if (is_array($data)) {
        return array_map('safeOutput', $data);
    }
    return htmlspecialchars($data ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Функция для генерации CSRF-токена
function generateCsrfToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Функция для проверки CSRF-токена
function verifyCsrfToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Функция для редиректа
function redirect($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

// Функция для получения IP-адреса пользователя
function getUserIp() {
    $keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim($_SERVER[$key]);
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}


/**
 * Форматирование телефона
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (strlen($phone) === 11) {
        return '+7 (' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7, 2) . '-' . substr($phone, 9, 2);
    }

    return $phone;
}


/**
 * Сохранить корзину в БД
 */
function saveOfferCart($data) {
    $db = getDbConnection();

    try {
        $stmt = $db->prepare("
            INSERT INTO offer_carts (cart_id, items, total_items, ip_address)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['cart_id'] ?? 'CART-' . time(),
            json_encode($data['items'] ?? [], JSON_UNESCAPED_UNICODE),
            $data['total_items'] ?? 0,
            getUserIp()
        ]);

        return $db->lastInsertId();

    } catch (Exception $e) {
        error_log("Ошибка сохранения корзины: " . $e->getMessage());
        return false;
    }
}

// Инициализация сессии
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Автоматическое обновление времени жизни сессии
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_LIFETIME)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();
?>
