<?php
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/checkout_validation.php';
require_once __DIR__ . '/includes/order.php';
require_once __DIR__ . '/includes/product_view.php';
require_once __DIR__ . '/includes/payment/payment_factory.php';
require_once __DIR__ . '/includes/notify/order_notifier.php';
require_once __DIR__ . '/includes/recaptcha.php';

/** Абсолютный URL возврата покупателя после оплаты (ЮKassa требует absolute). */
function checkout_return_url(string $orderNumber, string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/order_success.php?order=' . urlencode($orderNumber) . '&t=' . urlencode($token);
}

/** Ссылка «оплатить снова» для уже созданного заказа. */
function checkout_retry_url(string $orderNumber, string $token): string
{
    return '/checkout.php?pay=retry&order=' . urlencode($orderNumber) . '&t=' . urlencode($token);
}

function checkout_success_url(string $orderNumber, string $token): string
{
    return '/order_success.php?order=' . urlencode($orderNumber) . '&t=' . urlencode($token);
}

/**
 * Создать платёж для уже существующего заказа и привязать его.
 * @return array{ok:bool, url?:string, error?:string}
 */
function checkout_start_payment(array $order, array $items, string $accessToken): array
{
    if (!payment_gateway_configured()) {
        return ['ok' => false, 'error' => 'not_configured'];
    }
    // Платёж на 0 ₽ шлюз не примет — не идём в него вовсе.
    if ((float)($order['total'] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'zero_total'];
    }
    try {
        $payment = payment_gateway()->createPayment([
            'id' => (int)$order['id'],
            'order_number' => (string)$order['order_number'],
            'total' => $order['total'],
            'email' => $order['email'] ?? '',
            'phone' => $order['phone'] ?? '',
            'items' => $items,
        ], checkout_return_url((string)$order['order_number'], $accessToken));
        order_set_payment((int)$order['id'], $payment['payment_id']);
        return ['ok' => true, 'url' => $payment['confirmation_url']];
    } catch (Throwable $e) {
        error_log('createPayment failed for order ' . ($order['order_number'] ?? '?') . ': ' . $e->getMessage());
        return ['ok' => false, 'error' => 'gateway'];
    }
}

/** Отдать ответ клиенту, не дожидаясь завершения скрипта. */
function checkout_flush_response(): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // php-fpm: ответ ушёл, скрипт продолжает работу в фоне
        return;
    }
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @flush();
}

/**
 * Уведомления команде — ТОЛЬКО после того, как ответ отдан пользователю.
 * Раньше notify_new_order() стоял синхронно между созданием заказа и редиректом на
 * оплату: amoCRM (до 4 запросов × 30 с) и Telegram выжигали max_execution_time, и
 * пользователь получал белый экран уже с созданным заказом и очищенной корзиной.
 */
function checkout_notify_after_response(?array $order, array $items): void
{
    if ($order === null) { return; }
    ignore_user_abort(true);
    @set_time_limit((int)ceil(NOTIFY_TIME_BUDGET) + 5); // свой бюджет, независимый от оформления
    notify_new_order($order, $items);
}

/** Редирект пользователю, затем (уже вне его ожидания) — уведомления. */
function checkout_redirect_then_notify(string $url, ?array $order, array $items): void
{
    ignore_user_abort(true);
    header('Location: ' . $url, true, 303);
    header('Content-Length: 0');
    checkout_flush_response();
    checkout_notify_after_response($order, $items);
    exit;
}

$errors = [];
$old = [];
$payError = '';        // текст ошибки оплаты (заказ создан, платёж — нет)
$payOrderNumber = '';  // номер заказа для экрана ошибки оплаты
$payRetryUrl = '';
$paySuccessUrl = '';
$pendingNotify = null; // [order, items] — разошлём после рендера страницы

// --- Повторная попытка оплаты уже созданного заказа: /checkout.php?pay=retry ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['pay'] ?? '') === 'retry') {
    $rOrderNo = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_GET['order'] ?? ''));
    $rToken   = preg_replace('/[^a-f0-9]/', '', (string)($_GET['t'] ?? ''));
    $rOrder   = $rOrderNo !== '' ? order_get_by_number($rOrderNo) : null;
    if (!order_token_valid($rOrder, $rToken)) {
        $payError = 'Заказ не найден или ссылка устарела.';
    } elseif (($rOrder['status'] ?? '') !== 'pending_payment') {
        redirect(checkout_success_url($rOrderNo, $rToken)); // уже оплачен/отменён
    } else {
        $pay = checkout_start_payment($rOrder, order_items_get((int)$rOrder['id']), $rToken);
        if ($pay['ok']) {
            redirect($pay['url']);
        }
        $payOrderNumber = $rOrderNo;
        $payRetryUrl = checkout_retry_url($rOrderNo, $rToken);
        $paySuccessUrl = checkout_success_url($rOrderNo, $rToken);
        $payError = $pay['error'] === 'zero_total'
            ? 'Сумма заказа равна нулю — оплатить его нельзя. Свяжитесь с менеджером.'
            : 'Не удалось создать платёж. Заказ сохранён — попробуйте оплатить ещё раз.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['_'] = 'Сессия истекла, обновите страницу';
    } else {
        $old = $_POST;
        $v = checkout_validate($_POST);
        $lines = cart_session_lines();
        $issues = cart_checkout_issues($lines);
        if (!recaptcha_verify($_POST['recaptcha_token'] ?? '', 'checkout')) {
            $errors['_'] = 'Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.';
        } elseif (empty($_POST['pdn_consent'])) {
            $errors['_'] = 'Подтвердите согласие на обработку персональных данных.';
        } elseif (empty($lines)) {
            $errors['_'] = 'Корзина пуста';
        } elseif ($issues) {
            // Нулевая сумма / позиция без цены / снятый с продажи товар — заказ не создаём.
            $errors['_'] = implode(' ', $issues);
        } elseif (!$v['ok']) {
            $errors = $v['errors'];
        } else {
            $res = order_create($v['data'], $lines);
            if ($res['ok']) {
                cart_session_clear();
                $createdOrder = order_get((int)$res['order_id']);
                $orderItems = order_items_get((int)$res['order_id']);
                $successUrl = checkout_success_url($res['order_number'], $res['access_token']);

                if ($createdOrder === null) {
                    redirect($successUrl);
                }
                // Онлайн-оплата картой: создаём платёж в ЮKassa (с чеком 54-ФЗ) и уводим на её форму.
                if ($v['data']['payment_method'] === 'online') {
                    $pay = checkout_start_payment($createdOrder, $orderItems, $res['access_token']);
                    if ($pay['ok']) {
                        checkout_redirect_then_notify($pay['url'], $createdOrder, $orderItems);
                    }
                    if ($pay['error'] !== 'not_configured') {
                        // Платёж создать не удалось. НЕ показываем «Заказ принят»: заказ
                        // висит в pending_payment, честно сообщаем и даём повторить оплату.
                        $payOrderNumber = (string)$res['order_number'];
                        $payRetryUrl = checkout_retry_url($res['order_number'], $res['access_token']);
                        $paySuccessUrl = $successUrl;
                        $payError = $pay['error'] === 'zero_total'
                            ? 'Сумма заказа равна нулю — оплатить его нельзя. Свяжитесь с менеджером.'
                            : 'Заказ сохранён, но платёжная страница сейчас недоступна. Попробуйте оплатить ещё раз.';
                        $pendingNotify = [$createdOrder, $orderItems];
                    }
                }
                if ($payError === '') {
                    // Оплата по счёту или шлюз не настроен вовсе — обычный экран успеха.
                    checkout_redirect_then_notify($successUrl, $createdOrder, $orderItems);
                }
            } else {
                if ($res['error'] === 'empty_cart') {
                    $errors['_'] = 'Корзина пуста';
                } elseif ($res['error'] === 'invalid_cart') {
                    $errors['_'] = implode(' ', $res['issues'] ?? ['Корзину нельзя оформить']);
                } else {
                    $errors['_'] = 'Ошибка оформления, попробуйте позже';
                }
            }
        }
    }
}

// На POST с ошибкой валидации $lines уже посчитан выше — не гоняем каталог по БД
// повторно. На GET и на невалидном CSRF (где $lines не считался) — считаем сейчас.
$lines = $lines ?? cart_session_lines();
$totals = cart_totals($lines);
$cartIssues = cart_checkout_issues($lines);
$stockWarnings = cart_stock_warnings($lines);
$csrf = generateCsrfToken();
function old_val(array $old, string $k): string { return htmlspecialchars((string)($old[$k] ?? '')); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow"><!-- служебная страница покупателя -->
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
            <?php if ($payError !== ''): ?>
                <div class="no-products" style="text-align:center;padding:30px 0">
                    <i class="fas fa-triangle-exclamation" style="font-size:2.6rem;color:#f59e0b"></i>
                    <h2 style="margin:14px 0 6px">Оплата не началась</h2>
                    <?php if ($payOrderNumber !== ''): ?>
                        <p>Заказ <b><?= htmlspecialchars($payOrderNumber) ?></b> сохранён и ждёт оплаты.</p>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($payError) ?></p>
                    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:16px">
                        <?php if ($payRetryUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($payRetryUrl) ?>" class="btn btn-primary"><i class="fas fa-rotate-right"></i> Оплатить снова</a>
                        <?php endif; ?>
                        <?php if ($paySuccessUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($paySuccessUrl) ?>" class="btn btn-outline">Детали заказа</a>
                        <?php endif; ?>
                        <a href="/katalog_zip_paketov" class="btn btn-outline">В каталог</a>
                    </div>
                    <p style="margin-top:14px;color:var(--z-text-2,#64748b);font-size:.9rem">
                        Если оплата не проходит — позвоните нам: <?= htmlspecialchars(defined('SELLER_PHONE') ? SELLER_PHONE : '') ?>
                    </p>
                </div>
            <?php elseif (empty($lines)): ?>
                <div class="no-products"><p>Корзина пуста.</p><a href="/katalog_zip_paketov" class="btn btn-primary">В каталог</a></div>
            <?php else: ?>
                <?php if (!empty($errors['_'])): ?>
                    <div class="form-error" style="color:#dc2626;margin-bottom:12px"><?= htmlspecialchars($errors['_']) ?></div>
                <?php endif; ?>
                <?php if ($cartIssues): ?>
                    <div class="form-error" style="color:#dc2626;margin-bottom:12px;border:1px solid #fecaca;background:#fef2f2;border-radius:8px;padding:10px 12px">
                        <?php foreach ($cartIssues as $iss): ?><div><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($iss) ?></div><?php endforeach; ?>
                        <a href="/cart.php" style="text-decoration:underline">Вернуться в корзину</a>
                    </div>
                <?php endif; ?>
                <?php if ($stockWarnings): ?>
                    <div style="color:#92400e;margin-bottom:12px;border:1px solid #fde68a;background:#fffbeb;border-radius:8px;padding:10px 12px">
                        <?php foreach ($stockWarnings as $w): ?><div><i class="fas fa-clock"></i> <?= htmlspecialchars($w) ?></div><?php endforeach; ?>
                    </div>
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
                    <div class="form-group"><input type="text" name="delivery_address" placeholder="Адрес доставки (обязателен для курьера и ТК)" value="<?= old_val($old,'delivery_address') ?>"></div>
                    <?php if (!empty($errors['delivery_address'])): ?><small style="color:#dc2626"><?= htmlspecialchars($errors['delivery_address']) ?></small><?php endif; ?>
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
                    <label class="pdn-consent"><input type="checkbox" name="pdn_consent" value="1" <?= !empty($old['pdn_consent']) ? 'checked' : '' ?> required> Я даю <a href="/polconf.html" target="_blank">согласие на обработку персональных данных</a></label>
                    <button type="submit" class="btn btn-primary"<?= $cartIssues ? ' disabled' : '' ?>><i class="fas fa-check"></i> Оформить заказ</button>
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
<?php
// Страница отрендерена (экран «оплата не началась») — только теперь уведомляем команду.
if ($pendingNotify !== null) {
    checkout_flush_response();
    checkout_notify_after_response($pendingNotify[0], $pendingNotify[1]);
}
