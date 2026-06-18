<?php
require_once __DIR__ . '/../includes/cart.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (in_array($action, ['add', 'update', 'remove'], true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF']);
        exit;
    }
}

$id = (int)($_POST['id'] ?? 0);
$qty = (int)($_POST['qty'] ?? 1);

switch ($action) {
    case 'add':    cart_session_add($id, $qty); break;
    case 'update': cart_session_set($id, $qty); break;
    case 'remove': cart_session_remove($id); break;
    case 'get':    break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'bad action']);
        exit;
}

$lines = cart_session_lines();
$totals = cart_totals($lines);
echo json_encode([
    'success' => true,
    'count' => $totals['positions'],
    'total_qty' => $totals['total_qty'],
    'items_total' => $totals['items_total'],
]);
