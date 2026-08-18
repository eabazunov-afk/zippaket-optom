<?php
require_once __DIR__ . '/../includes/cart.php';
header('Content-Type: application/json; charset=utf-8');

/** Ответ об ошибке и выход. */
function cart_api_fail(string $message, int $code = 422): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (in_array($action, ['add', 'update', 'remove'], true)) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        cart_api_fail('CSRF', 403);
    }
}

$id = (int)($_POST['id'] ?? 0);
$rawQty = $_POST['qty'] ?? null;
$notice = '';

switch ($action) {
    case 'add':
    case 'update':
        if ($id <= 0) {
            cart_api_fail('Товар не указан');
        }
        $product = (new Catalog())->getProductById($id);
        if (!$product) {
            cart_api_fail('Товар не найден или снят с продажи');
        }
        // Товар без цены («Цена по запросу») нельзя положить в корзину: иначе заказ
        // уходит на 0 ₽ и платёж создать невозможно.
        if (!isset($product['price_rub']) || $product['price_rub'] === null || (float)$product['price_rub'] <= 0) {
            cart_api_fail('Цена этого товара рассчитывается индивидуально — запросите КП у менеджера');
        }

        $parsed = cart_parse_qty_input($rawQty);
        if (!$parsed['ok']) {
            if ($action === 'add' && in_array($parsed['error'], ['missing', 'empty'], true)) {
                // Кнопка «В корзину» без поля количества — берём минимальную партию.
                $parsed = ['ok' => true, 'qty' => (int)($product['min_order_qty'] ?? 1), 'clamped' => false];
            } elseif ($parsed['error'] === 'empty' || $parsed['error'] === 'missing') {
                // Пустое поле — это НЕ «удалить»: ничего не меняем, просим ввести число.
                cart_api_fail('Укажите количество');
            } else {
                cart_api_fail('Количество должно быть целым числом');
            }
        }
        $qty = (int)$parsed['qty'];
        if (!empty($parsed['clamped'])) {
            $notice = 'Максимум ' . number_format(CART_MAX_QTY, 0, ',', ' ') . ' шт в одной позиции — количество уменьшено.';
        }

        if ($action === 'update' && $qty === 0) {
            cart_session_remove($id);   // явный 0 в поле = удалить позицию
            break;
        }

        cart_session_remember_name($id, (string)$product['full_name']);
        if ($action === 'add') {
            cart_session_add($id, $qty);
        } else {
            cart_session_set($id, $qty);
        }

        // Наличие: под заказ производится, поэтому предупреждаем, а не блокируем.
        $stock = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : null;
        $inCart = cart_session_raw()[$id] ?? 0;
        if ($stock !== null && $inCart > $stock) {
            $notice = trim($notice . ' В наличии ' . number_format($stock, 0, ',', ' ')
                . ' шт, остальное изготовим под заказ — срок согласует менеджер.');
        }
        break;

    case 'remove':
        cart_session_remove($id);
        break;

    case 'get':
        break;

    default:
        cart_api_fail('bad action', 400);
}

$lines = cart_session_lines();
$totals = cart_totals($lines);
echo json_encode([
    'success' => true,
    'count' => $totals['positions'],
    'total_qty' => $totals['total_qty'],
    'items_total' => $totals['items_total'],
    'message' => $notice,
    'issues' => cart_checkout_issues($lines),
], JSON_UNESCAPED_UNICODE);
