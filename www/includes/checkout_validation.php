<?php
// Чистая валидация данных оформления заказа. Без БД/сессии.
function checkout_validate(array $in): array
{
    $errors = [];
    $name  = trim((string)($in['customer_name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $type  = (string)($in['customer_type'] ?? 'individual');
    $delivery = (string)($in['delivery_method'] ?? '');
    $payment  = (string)($in['payment_method'] ?? '');
    $company = trim((string)($in['company_name'] ?? ''));
    $inn = trim((string)($in['inn'] ?? ''));
    $kpp = trim((string)($in['kpp'] ?? ''));
    $legal = trim((string)($in['legal_address'] ?? ''));
    $address = trim((string)($in['delivery_address'] ?? ''));
    $comment = trim((string)($in['comment'] ?? ''));

    if ($name === '') {
        $errors['customer_name'] = 'Укажите имя';
    }
    if (!preg_match('/^[\d\s\-\+\(\)]{10,20}$/', $phone)) {
        $errors['phone'] = 'Укажите корректный телефон';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный email';
    }
    if (!in_array($type, ['individual', 'company'], true)) {
        $errors['customer_type'] = 'Некорректный тип клиента';
    }
    if ($type === 'company') {
        if ($company === '') {
            $errors['company_name'] = 'Укажите название организации';
        }
        if (!preg_match('/^(\d{10}|\d{12})$/', $inn)) {
            $errors['inn'] = 'Укажите корректный ИНН (10 или 12 цифр)';
        }
    }
    if (!in_array($delivery, ['pickup', 'courier', 'tk'], true)) {
        $errors['delivery_method'] = 'Выберите способ доставки';
    }
    if (!in_array($payment, ['online', 'invoice'], true)) {
        $errors['payment_method'] = 'Выберите способ оплаты';
    } elseif ($payment === 'invoice' && $type !== 'company') {
        $errors['payment_method'] = 'Оплата по счёту доступна только юрлицам';
    }

    $data = [
        'customer_type' => $type,
        'customer_name' => $name,
        'phone' => $phone,
        'email' => $email,
        'company_name' => $type === 'company' ? $company : null,
        'inn' => $type === 'company' ? $inn : null,
        'kpp' => $type === 'company' ? ($kpp !== '' ? $kpp : null) : null,
        'legal_address' => $type === 'company' ? ($legal !== '' ? $legal : null) : null,
        'needs_invoice' => !empty($in['needs_invoice']) ? 1 : 0,
        'delivery_method' => $delivery,
        'delivery_address' => $address !== '' ? $address : null,
        'comment' => $comment !== '' ? $comment : null,
        'payment_method' => $payment,
    ];

    return ['ok' => empty($errors), 'errors' => $errors, 'data' => $data];
}
