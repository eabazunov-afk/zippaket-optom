<?php
/**
 * Доступ к настройкам приложения (таблица `settings`, key-value).
 *
 * Читает/пишет отдельные настройки с кэшем на запрос и безопасным фолбэком:
 * если таблицы ещё нет (миграция не накатана) или коннекта нет — возвращает
 * дефолт, а не падает. Это важно: прод/локальная БД могут отставать от кода.
 */

require_once __DIR__ . '/config.php';

/** Дефолтные цены сырья калькулятора (₽/кг). Единственный источник дефолтов. */
function calc_default_material_prices(): array {
    return ['EVA' => 380.0, 'ПВД' => 360.0];
}

/**
 * Прочитать настройку по ключу. При любой ошибке БД или отсутствии значения
 * возвращает $default. Кэширует прочитанные значения в пределах запроса.
 */
function get_setting(string $key, $default = null) {
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $db = getDbConnection();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $result = ($value === false) ? $default : $value;
    } catch (Throwable $e) {
        error_log('get_setting("' . $key . '") fallback to default: ' . $e->getMessage());
        $result = $default;
    }

    $cache[$key] = $result;
    return $result;
}

/**
 * Записать/обновить настройку (UPSERT). Возвращает true при успехе.
 */
function set_setting(string $key, string $value): bool {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
        return true;
    } catch (Throwable $e) {
        error_log('set_setting("' . $key . '") failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Итоговые цены материалов калькулятора.
 *
 * Чистая функция: мёржит $overrides поверх дефолтов, приводит к float и
 * отбрасывает неположительные/нечисловые значения (возвращая для них дефолт).
 * Не обращается к БД — тестируется изолированно.
 *
 * @param array $overrides ['EVA' => 500, 'ПВД' => '410', ...]
 * @return array ['EVA' => float, 'ПВД' => float]
 */
function calc_material_prices(array $overrides = []): array {
    $prices = calc_default_material_prices();

    foreach ($prices as $material => $default) {
        if (!array_key_exists($material, $overrides)) {
            continue;
        }
        $value = $overrides[$material];
        if (is_numeric($value) && (float)$value > 0) {
            $prices[$material] = (float)$value;
        }
        // иначе оставляем дефолт
    }

    return $prices;
}

/**
 * Цены материалов калькулятора из настроек БД (с фолбэком на дефолты).
 */
function calc_material_prices_from_settings(): array {
    return calc_material_prices([
        'EVA' => get_setting('calc_price_eva', null),
        'ПВД' => get_setting('calc_price_pvd', null),
    ]);
}
