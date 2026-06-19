<?php
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/checkout_validation.php';
require_once __DIR__ . '/includes/order.php';
require_once __DIR__ . '/includes/product_view.php';
require_once __DIR__ . '/includes/payment/payment_factory.php';
require_once __DIR__ . '/includes/notify/order_notifier.php';
require_once __DIR__ . '/includes/recaptcha.php';

/** Абсолютный URL возврата покупателя после оплаты (ЮKassa требует absolute). */
function checkout_return_url(string $orderNumber): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/order_success.php?order=' . urlencode($orderNumber);
}

$errors = [];
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['_'] = 'Сессия истекла, обновите страницу';
    } else {
        $old = $_POST;
        $v = checkout_validate($_POST);
        $lines = cart_session_lines();
        if (!recaptcha_verify($_POST['recaptcha_token'] ?? '', 'checkout')) {
            $errors['_'] = 'Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.';
        } elseif (empty($lines)) {
            $errors['_'] = 'Корзина пуста';
        } elseif (!$v['ok']) {
            $errors = $v['errors'];
        } else {
            $res = order_create($v['data'], $lines);
            if ($res['ok']) {
                cart_session_clear();
                // Уведомляем команду о новом заказе (best-effort, не влияет на флоу).
                $createdOrder = order_get((int)$res['order_id']);
                $orderItems = order_items_get((int)$res['order_id']);
                if ($createdOrder !== null) {
                    notify_new_order($createdOrder, $orderItems);
                }
                // Онлайн-оплата картой: создаём платёж в ЮKassa (с чеком 54-ФЗ) и уводим на её форму.
                if ($v['data']['payment_method'] === 'online' && payment_gateway_configured()) {
                    try {
                        $payment = payment_gateway()->createPayment([
                            'id' => $res['order_id'],
                            'order_number' => $res['order_number'],
                            'total' => $res['total'],
                            'email' => $createdOrder['email'] ?? '',
                            'phone' => $createdOrder['phone'] ?? '',
                            'items' => $orderItems,
                        ], checkout_return_url($res['order_number']));
                        order_set_payment($res['order_id'], $payment['payment_id']);
                        redirect($payment['confirmation_url']);
                    } catch (Throwable $e) {
                        // Заказ уже создан (pending_payment). Мягкая деградация: ведём на success,
                        // менеджер свяжется и выставит оплату вручную.
                        error_log('createPayment failed for order ' . $res['order_number'] . ': ' . $e->getMessage());
                    }
                }
                redirect('/order_success.php?order=' . urlencode($res['order_number']));
            } else {
                $errors['_'] = $res['error'] === 'empty_cart' ? 'Корзина пуста' : 'Ошибка оформления, попробуйте позже';
            }
        }
    }
}

$lines = cart_session_lines();
$totals = cart_totals($lines);
$csrf = generateCsrfToken();
function old_val(array $old, string $k): string { return htmlspecialchars((string)($old[$k] ?? '')); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа | ZLOCK</title>
    <link rel="icon" href="/images/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/catalog.css">
    <link rel="stylesheet" href="/css/premium.css">
    <link rel="stylesheet" href="/css/shop-dark.css">
    <script src="https://www.google.com/recaptcha/api.js?render=6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf"></script>
</head>
<body class="premium zlock">
<div class="site-wrapper">
    <?php include __DIR__ . '/header.php'; ?>
    <main class="main-content">
        <section class="catalog-section"><div class="container">
            <div class="pm-pagehead"><h1>Оформление заказа</h1><div class="pm-sub">Безопасная оплата · данные защищены</div></div>
            <?php if (empty($lines)): ?>
                <div class="no-products"><p>Корзина пуста.</p><a href="/katalog_zip_paketov" class="btn btn-primary">В каталог</a></div>
            <?php else: ?>
                <?php if (!empty($errors['_'])): ?>
                    <div class="form-error" style="color:#dc2626;margin-bottom:12px"><?= htmlspecialchars($errors['_']) ?></div>
                <?php endif; ?>
                <form id="checkoutForm" method="POST" action="/checkout.php" style="max-width:560px">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="recaptcha_token" id="checkoutRecaptchaToken">

                    <label class="field-label">Кто оформляет</label>
                    <div class="ctype-options">
                        <label class="ctype-opt"><input type="radio" name="customer_type" value="individual" <?= (($old['customer_type'] ?? 'individual') === 'individual') ? 'checked' : '' ?>><i class="fas fa-user"></i><span>Физлицо</span></label>
                        <label class="ctype-opt"><input type="radio" name="customer_type" value="company" <?= (($old['customer_type'] ?? '') === 'company') ? 'checked' : '' ?>><i class="fas fa-building"></i><span>Юрлицо</span></label>
                    </div>

                    <div class="form-group"><input type="text" name="customer_name" placeholder="Имя *" value="<?= old_val($old,'customer_name') ?>"></div>
                    <?php if (!empty($errors['customer_name'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['customer_name']) ?></small><?php endif; ?>
                    <div class="form-group"><input type="tel" name="phone" placeholder="Телефон *" value="<?= old_val($old,'phone') ?>"></div>
                    <?php if (!empty($errors['phone'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['phone']) ?></small><?php endif; ?>
                    <div class="form-group"><input type="email" name="email" placeholder="Email" value="<?= old_val($old,'email') ?>"></div>
                    <?php if (!empty($errors['email'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['email']) ?></small><?php endif; ?>

                    <div id="companyFields" style="border:1px dashed #c9d6e5;border-radius:8px;padding:10px;margin:10px 0">
                        <div class="form-group"><input type="text" name="company_name" placeholder="Организация" value="<?= old_val($old,'company_name') ?>"></div>
                        <?php if (!empty($errors['company_name'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['company_name']) ?></small><?php endif; ?>
                        <div class="form-group"><input type="text" name="inn" placeholder="ИНН" value="<?= old_val($old,'inn') ?>"></div>
                        <?php if (!empty($errors['inn'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['inn']) ?></small><?php endif; ?>
                        <div class="form-group"><input type="text" name="kpp" placeholder="КПП" value="<?= old_val($old,'kpp') ?>"></div>
                        <div class="form-group"><input type="text" name="legal_address" placeholder="Юр. адрес" value="<?= old_val($old,'legal_address') ?>"></div>
                        <label><input type="checkbox" name="needs_invoice" value="1" <?= !empty($old['needs_invoice']) ? 'checked' : '' ?>> Нужен счёт</label>
                    </div>

                    <div class="form-group">
                        <label>Доставка:</label>
                        <select name="delivery_method">
                            <option value="pickup" <?= (($old['delivery_method'] ?? '')==='pickup')?'selected':'' ?>>Самовывоз</option>
                            <option value="courier" <?= (($old['delivery_method'] ?? '')==='courier')?'selected':'' ?>>Курьер</option>
                            <option value="tk" <?= (($old['delivery_method'] ?? '')==='tk')?'selected':'' ?>>Транспортная компания</option>
                        </select>
                    </div>
                    <?php if (!empty($errors['delivery_method'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['delivery_method']) ?></small><?php endif; ?>
                    <div class="form-group"><input type="text" name="delivery_address" placeholder="Адрес доставки" value="<?= old_val($old,'delivery_address') ?>"></div>
                    <div class="form-group"><textarea name="comment" placeholder="Комментарий" rows="2"><?= old_val($old,'comment') ?></textarea></div>

                    <div class="form-group">
                        <label class="field-label">Способ оплаты</label>
                        <div class="pay-options">
                            <label class="pay-opt">
                                <input type="radio" name="payment_method" value="online" <?= (($old['payment_method'] ?? 'online')==='online')?'checked':'' ?>>
                                <span class="pay-ic"><i class="fas fa-credit-card"></i></span>
                                <span class="pay-txt">
                                    <b>Картой онлайн</b>
                                    <span class="pay-brands"><i class="fab fa-cc-visa"></i><i class="fab fa-cc-mastercard"></i><i class="fab fa-cc-mir"></i><i class="fas fa-mobile-screen-button"></i></span>
                                    <small>Visa · Mastercard · МИР · СБП</small>
                                </span>
                            </label>
                            <label class="pay-opt" id="invoiceOpt">
                                <input type="radio" name="payment_method" value="invoice" <?= (($old['payment_method'] ?? '')==='invoice')?'checked':'' ?>>
                                <span class="pay-ic"><i class="fas fa-file-invoice-dollar"></i></span>
                                <span class="pay-txt">
                                    <b>По счёту</b>
                                    <small>Для юрлиц · оплата по реквизитам</small>
                                </span>
                            </label>
                        </div>
                    </div>
                    <?php if (!empty($errors['payment_method'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['payment_method']) ?></small><?php endif; ?>

                    <div style="margin:14px 0;font-size:1.1rem">К оплате: <b><?= pv_format_price($totals['items_total']) ?></b></div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Оформить заказ</button>
                    <p style="font-size:0.8rem;color:#94a3b8;margin-top:8px">Нажимая кнопку, вы соглашаетесь с <a href="/polconf.html">политикой конфиденциальности</a>.</p>
                </form>
            <?php endif; ?>
        </div></section>
    </main>
    <?php include __DIR__ . '/footer.php'; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var radios = document.querySelectorAll('input[name="customer_type"]');
  var company = document.getElementById('companyFields');
  var invoiceOpt = document.getElementById('invoiceOpt');
  var invoiceRadio = invoiceOpt ? invoiceOpt.querySelector('input') : null;
  function sync() {
    var isCompany = document.querySelector('input[name="customer_type"]:checked').value === 'company';
    company.style.display = isCompany ? 'block' : 'none';
    invoiceOpt.classList.toggle('disabled', !isCompany);
    if (invoiceRadio) invoiceRadio.disabled = !isCompany;
    if (!isCompany && invoiceRadio && invoiceRadio.checked) {
      document.querySelector('input[name="payment_method"][value="online"]').checked = true;
    }
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();

  // reCAPTCHA v3 на оформлении
  var form = document.getElementById('checkoutForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.recaptchaDone) return; // повторный сабмит после получения токена
      e.preventDefault();
      if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
        grecaptcha.ready(function () {
          grecaptcha.execute('6Lfd5FksAAAAAGQNGm2ny-aJhjuw6Mp5th7SNJRf', { action: 'checkout' }).then(function (token) {
            document.getElementById('checkoutRecaptchaToken').value = token;
            form.dataset.recaptchaDone = '1';
            form.submit();
          });
        });
      } else {
        form.dataset.recaptchaDone = '1';
        form.submit();
      }
    });
  }
});
</script>
</body>
</html>
