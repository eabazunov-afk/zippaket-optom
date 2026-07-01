<?php
// Поиск реквизитов по ИНН (api-fns.ru) — ВНУТРЕННИЙ инструмент, только для авторизованных
// администраторов. Ключ ФНС — из config (FNS_API_KEY), не в коде. Без CORS *.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../admin/includes/security_config.php';
require_once __DIR__ . '/../admin/includes/auth.php';
require_once __DIR__ . '/../admin/includes/permissions.php';

checkAdminAuth();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён'], JSON_UNESCAPED_UNICODE);
    exit;
}

$key = defined('FNS_API_KEY') ? FNS_API_KEY : '';
if ($key === '' || strpos($key, 'ВАШ_') === 0) {
    http_response_code(503);
    echo json_encode(['error' => 'Ключ ФНС не настроен'], JSON_UNESCAPED_UNICODE);
    exit;
}

$inn = preg_replace('/[^0-9]/', '', $_GET['inn'] ?? '');
$type = $_GET['type'] ?? 'auto';

if (strlen($inn) !== 10 && strlen($inn) !== 12) {
    echo json_encode(['error' => 'Некорректный ИНН'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Запрос к ФНС через curl с таймаутом
$url = 'https://api-fns.ru/api/egr?req=' . urlencode($inn) . '&key=' . urlencode($key);
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$response = curl_exec($ch);
curl_close($ch);
$data = $response ? json_decode($response, true) : null;

if (!$data || !isset($data['items'][0])) {
    echo json_encode(['error' => 'Организация не найдена'], JSON_UNESCAPED_UNICODE);
    exit;
}

$company = $data['items'][0];
$isIp = isset($company['ИП']);
$entity = $isIp ? $company['ИП'] : ($company['ЮЛ'] ?? []);

if ($type === 'ip' && !$isIp) { echo json_encode(['error' => 'Это не ИП'], JSON_UNESCAPED_UNICODE); exit; }
if ($type === 'ooo' && $isIp) { echo json_encode(['error' => 'Это не ООО'], JSON_UNESCAPED_UNICODE); exit; }

$result = [
    'type' => $isIp ? 'ИП' : 'ООО',
    'inn' => $isIp ? ($entity['ИННФЛ'] ?? '') : ($entity['ИНН'] ?? ''),
    'kpp' => $entity['КПП'] ?? '',
    'ogrn' => $isIp ? ($entity['ОГРНИП'] ?? '') : ($entity['ОГРН'] ?? ''),
    'ogrn_date' => $entity['ДатаОГРН'] ?? '',
    'name_full' => $isIp ? ($entity['ФИОПолн'] ?? '') : ($entity['НаимПолнЮЛ'] ?? ''),
    'name_short' => $isIp ? ($entity['ФИО'] ?? '') : ($entity['НаимСокрЮЛ'] ?? ''),
    'status' => $entity['Статус'] ?? '',
    'address' => $entity['Адрес']['АдресПолн'] ?? $entity['АдресПолн'] ?? '',
    'director' => $entity['Руководитель']['ФИОПолн'] ?? '',
    'director_position' => $entity['Руководитель']['Должн'] ?? '',
    'kpp_present' => !empty($entity['КПП']),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
