<?php
// Локальный dev-роутер для встроенного PHP-сервера.
// Эмулирует ключевые правила .htaccess (Apache mod_rewrite),
// которые сам PHP built-in server не выполняет.
// Запуск: php -S 127.0.0.1:8080 -t www www/_dev_router.php

$root = __DIR__;
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = realpath($root . urldecode($uri));

// Реальные существующие файлы (css/js/img/*.php) отдаём/исполняем как есть
if ($uri !== '/' && $path && is_file($path)) {
    return false;
}

// ЧПУ карточки товара: /product/123 -> product.php?id=123
if (preg_match('#^/product/(\d+)/?$#', $uri, $m)) {
    $_GET['id'] = $_REQUEST['id'] = $m[1];
    require $root . '/product.php';
    return true;
}

// Каталог: /katalog_zip_paketov(/...) -> katalog_zip_paketov/katalog.php
if (preg_match('#^/katalog_zip_paketov(/.*)?$#', $uri)) {
    require $root . '/katalog_zip_paketov/katalog.php';
    return true;
}

// Динамический sitemap
if ($uri === '/sitemap.xml') {
    require $root . '/sitemap.php';
    return true;
}

// Спец-редирект логотипа
if (preg_match('#^/zip_paket_s_logotipom/?$#', $uri)) {
    require $root . '/zip_lock_logo.php';
    return true;
}

// Каталог/директория с index.php (включая '/')
if ($path && is_dir($path) && is_file($path . '/index.php')) {
    require $path . '/index.php';
    return true;
}

// Остальное — стандартная обработка (404 для отсутствующего)
return false;
