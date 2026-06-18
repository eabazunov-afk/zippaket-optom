<?php
/**
 * Router для встроенного PHP-сервера (php -S), эмулирует ЧПУ из www/.htaccess.
 * Прод-only правила (форс HTTPS, www->без www, 301-канонизация) намеренно опущены —
 * локально они дают редирект-петли.
 *
 * Запуск:  php -S localhost:8000 -t www router.php
 */

$docroot = $_SERVER['DOCUMENT_ROOT'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rawurldecode($uri);

// Реальные существующие файлы (css/js/images/*.php) отдаём напрямую.
$real = realpath($docroot . $uri);
if ($uri !== '/' && $real !== false && is_file($real)) {
    return false; // встроенный сервер обслужит сам
}

// /product/123 -> product.php?id=123
if (preg_match('#^/product/([0-9]+)/?$#', $uri, $m)) {
    $_GET['id'] = $_REQUEST['id'] = $m[1];
    $_SERVER['SCRIPT_NAME'] = '/product.php';
    $_SERVER['SCRIPT_FILENAME'] = $docroot . '/product.php';
    require $docroot . '/product.php';
    return true;
}

// /zip_paket_s_logotipom -> zip_lock_logo.php
if (preg_match('#^/zip_paket_s_logotipom/?$#', $uri)) {
    require $docroot . '/zip_lock_logo.php';
    return true;
}

// /katalog_zip_paketov[/...] -> katalog_zip_paketov/katalog.php (QSA сохраняется автоматически)
if (preg_match('#^/katalog_zip_paketov(/.*)?$#', $uri)) {
    $_SERVER['SCRIPT_NAME'] = '/katalog_zip_paketov/katalog.php';
    $_SERVER['SCRIPT_FILENAME'] = $docroot . '/katalog_zip_paketov/katalog.php';
    require $docroot . '/katalog_zip_paketov/katalog.php';
    return true;
}

// Корень -> index.php
if ($uri === '/' || $uri === '') {
    require $docroot . '/index.php';
    return true;
}

// Остальное — пусть сервер вернёт файл или 404
return false;
