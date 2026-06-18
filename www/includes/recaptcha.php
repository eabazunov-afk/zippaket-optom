<?php
require_once __DIR__ . '/config.php';

/**
 * Проверка токена reCAPTCHA v3.
 * Best-effort: если ключ не настроен или Google недоступен — НЕ блокируем заказ
 * (степень защиты не должна ронять конверсию из-за сети). Жёстко отклоняем только
 * явный success=false или низкий score.
 *
 * @param string $token  значение g-recaptcha-response из формы
 * @param string $expectedAction  ожидаемый action (например 'checkout')
 * @param float  $minScore  минимальный допустимый score
 */
function recaptcha_verify(string $token, string $expectedAction = '', float $minScore = 0.5): bool
{
    if (!defined('RECAPTCHA_SECRET_KEY') || RECAPTCHA_SECRET_KEY === '' || strpos(RECAPTCHA_SECRET_KEY, 'ВАШ_') === 0) {
        return true; // не настроено — пропускаем
    }
    if ($token === '') {
        return false;
    }
    try {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => RECAPTCHA_SECRET_KEY,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            error_log('recaptcha network fail: ' . curl_error($ch));
            curl_close($ch);
            return true; // деградация: не блокируем при сбое сети
        }
        curl_close($ch);
        return recaptcha_evaluate($resp, $expectedAction, $minScore);
    } catch (Throwable $e) {
        error_log('recaptcha error: ' . $e->getMessage());
        return true;
    }
}

/**
 * Чистая оценка ответа siteverify (тестируется без сети).
 * @param string $rawJson  тело ответа Google
 */
function recaptcha_evaluate(string $rawJson, string $expectedAction = '', float $minScore = 0.5): bool
{
    $d = json_decode($rawJson, true);
    if (!is_array($d)) {
        return true; // невалидный ответ — не блокируем
    }
    if (empty($d['success'])) {
        return false;
    }
    if (isset($d['score']) && (float)$d['score'] < $minScore) {
        return false;
    }
    if ($expectedAction !== '' && isset($d['action']) && $d['action'] !== $expectedAction) {
        return false;
    }
    return true;
}
