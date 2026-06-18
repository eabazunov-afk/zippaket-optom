<?php
// get_company_full.php - Полные данные для счета
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$key = '54c1347f40d246e882893ccfe2e1fe1c56d8653f';
$inn = $_GET['inn'] ?? '';
$type = $_GET['type'] ?? 'auto'; // auto, ip, ooo

if (!$inn) {
    die(json_encode(['error' => 'Нет ИНН']));
}

// Очищаем ИНН
$inn = preg_replace('/[^0-9]/', '', $inn);

// Формируем запрос к API ФНС
$url = "https://api-fns.ru/api/egr?req=" . urlencode($inn) . "&key=" . urlencode($key);
$response = file_get_contents($url);
$data = json_decode($response, true);

if (!$data || !isset($data['items'][0])) {
    die(json_encode(['error' => 'Организация не найдена']));
}

$company = $data['items'][0];
$isIp = isset($company['ИП']);
$entity = $isIp ? $company['ИП'] : $company['ЮЛ'];

// Проверяем соответствие типу
if ($type == 'ip' && !$isIp) {
    die(json_encode(['error' => 'Это не ИП']));
}
if ($type == 'ooo' && $isIp) {
    die(json_encode(['error' => 'Это не ООО']));
}

// Формируем полные данные для счета
$result = [
    'type' => $isIp ? 'ИП' : 'ООО',
    'inn' => $isIp ? $entity['ИННФЛ'] : $entity['ИНН'],
    'kpp' => $entity['КПП'] ?? '',
    'ogrn' => $isIp ? $entity['ОГРНИП'] : $entity['ОГРН'],
    'ogrn_date' => $entity['ДатаОГРН'] ?? '',
    'name_full' => $isIp ? $entity['ФИОПолн'] : $entity['НаимПолнЮЛ'],
    'name_short' => $isIp ? $entity['ФИО'] : $entity['НаимСокрЮЛ'],
    'status' => $entity['Статус'] ?? '',
    'address' => $entity['Адрес']['АдресПолн'] ?? $entity['АдресПолн'] ?? '',
    'address_details' => [
        'index' => $entity['Адрес']['Индекс'] ?? '',
        'region' => $entity['Адрес']['АдресДетали']['Регион']['Наим'] ?? '',
        'city' => $entity['Адрес']['АдресДетали']['Город']['Наим'] ?? $entity['Адрес']['АдресДетали']['НаселенныйПункт']['Наим'] ?? '',
        'street' => $entity['Адрес']['АдресДетали']['Улица']['Наим'] ?? '',
        'house' => $entity['Адрес']['АдресДетали']['Дом'] ?? ''
    ],
    'director' => $entity['Руководитель']['ФИОПолн'] ?? '',
    'director_position' => $entity['Руководитель']['Должн'] ?? '',
    'registration_date' => $entity['ДатаРег'] ?? '',
    'okpo' => $entity['КодыСтат']['ОКПО'] ?? '',
    'oktmo' => $entity['КодыСтат']['ОКТМО'] ?? '',
    'okfs' => $entity['КодыСтат']['ОКФС'] ?? '',
    'okogu' => $entity['КодыСтат']['ОКОГУ'] ?? '',
    'okopf' => $entity['КодОКОПФ'] ?? '',
    'okopf_name' => $entity['ОКОПФ'] ?? '',
    'capital' => $entity['Капитал']['СумКап'] ?? '',
    'phone' => $entity['Контакты']['Телефон'] ?? '',
    'email' => $entity['Контакты']['Email'] ?? '',
    'website' => $entity['Контакты']['Сайт'] ?? '',
    
    // Дополнительные данные, которых нет в ФНС (заполняются вручную)
    'bank' => [
        'name' => '',
        'bik' => '',
        'account' => '',
        'corr_account' => ''
    ],
    'invoice' => [
        'number' => '',
        'date' => date('Y-m-d'),
        'items' => []
    ]
];

// Добавляем учредителей если есть
if (!empty($entity['Учредители'])) {
    $result['founders'] = $entity['Учредители'];
}

// Добавляем виды деятельности если есть
if (!empty($entity['ОКВЭД'])) {
    $result['okved'] = $entity['ОКВЭД'];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>