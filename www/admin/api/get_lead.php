<?php
require_once '../../includes/init.php';
require_once '../includes/security_config.php';
require_once '../includes/auth.php';

// Разрешить доступ только из админки
$allowed_referer = '/admin/';
if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], $allowed_referer) === false) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['success' => false, 'error' => 'Direct access not allowed']));
}

// Проверка авторизации
checkAdminAuth();

// Проверка авторизации
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID не указан']);
    exit;
}

$id = (int)$_GET['id'];
$db = getDbConnection();

try {
    $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    $lead = $stmt->fetch();
    
    if ($lead) {
        echo json_encode([
            'success' => true,
            'lead' => [
                'id' => $lead['id'],
                'name' => $lead['name'],
                'phone' => $lead['phone'],
                'email' => $lead['email'],
                'status' => $lead['status'],
                'comment' => $lead['comment'],
                'created_at' => $lead['created_at'],
                'updated_at' => $lead['updated_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Заявка не найдена']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
}
?>