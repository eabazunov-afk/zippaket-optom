<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/order.php';
$orderNumber = isset($_GET['order']) ? preg_replace('/[^A-Za-z0-9\-]/', '', $_GET['order']) : '';

$order = $orderNumber !== '' ? order_get_by_number($orderNumber) : null;
$orderStatus = $order['status'] ?? '';
// Текст статуса для покупателя.
$statusText = [
    'paid'            => ['Оплачен. Спасибо! Мы приступаем к выполнению заказа.', '#10b981', 'fa-check-circle'],
    'pending_payment' => ['Ожидает оплаты. Если вы не завершили оплату — мы свяжемся с вами.', '#f59e0b', 'fa-clock'],
    'canceled'        => ['Платёж отменён. Заказ можно оформить заново или связаться с нами.', '#dc2626', 'fa-times-circle'],
][$orderStatus] ?? ['Заказ принят. Мы свяжемся с вами для подтверждения и согласования доставки.', '#10b981', 'fa-check-circle'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ оформлен | ZLOCK</title>
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/premium.css">
</head>
<body class="premium">
<div class="site-wrapper">
    <?php include __DIR__ . '/header.php'; ?>
    <main class="main-content">
        <section class="catalog-section"><div class="container" style="text-align:center;padding:40px 0">
            <i class="fas <?= $statusText[2] ?>" style="font-size:3rem;color:<?= $statusText[1] ?>"></i>
            <h1>Заказ оформлен</h1>
            <?php if ($orderNumber): ?>
                <p>Номер заказа: <b><?= htmlspecialchars($orderNumber) ?></b></p>
            <?php endif; ?>
            <p><?= htmlspecialchars($statusText[0]) ?></p>
            <a href="/katalog_zip_paketov" class="btn btn-primary">Продолжить покупки</a>
        </div></section>
    </main>
    <?php include __DIR__ . '/footer.php'; ?>
</div>
</body>
</html>
