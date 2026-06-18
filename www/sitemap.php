<?php
/**
 * Динамический sitemap.xml (главная, статические страницы, категории, товары).
 * Отдаётся по чистому URL /sitemap.xml (rewrite в .htaccess), т.к. robots.txt
 * закрывает *.php от индексации.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: application/xml; charset=utf-8');

$base = seo_base();
$today = date('Y-m-d');

/** Экранирование URL для XML. */
function sm_loc(string $url): string
{
    return htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$urls = [];
$urls[] = ['loc' => $base . '/', 'changefreq' => 'daily', 'priority' => '1.0', 'lastmod' => $today];
$urls[] = ['loc' => $base . '/katalog_zip_paketov/', 'changefreq' => 'daily', 'priority' => '0.9', 'lastmod' => $today];
$urls[] = ['loc' => $base . '/zip_paket_s_logotipom/', 'changefreq' => 'monthly', 'priority' => '0.7', 'lastmod' => $today];
$urls[] = ['loc' => $base . '/polconf.html', 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $today];

try {
    $db = getDbConnection();

    // Категории каталога
    $cats = $db->query("SELECT DISTINCT category FROM products WHERE is_active = 1 AND category <> '' ORDER BY category")->fetchAll();
    foreach ($cats as $c) {
        $urls[] = [
            'loc' => $base . '/katalog_zip_paketov/?category=' . rawurlencode($c['category']),
            'changefreq' => 'weekly',
            'priority' => '0.7',
            'lastmod' => $today,
        ];
    }

    // Товары
    $prods = $db->query("SELECT id, updated_at FROM products WHERE is_active = 1 ORDER BY id")->fetchAll();
    foreach ($prods as $p) {
        $urls[] = [
            'loc' => $base . '/product/' . (int)$p['id'],
            'changefreq' => 'weekly',
            'priority' => '0.8',
            'lastmod' => !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : $today,
        ];
    }
} catch (Throwable $e) {
    error_log('sitemap error: ' . $e->getMessage());
    // отдаём хотя бы статические URL
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . sm_loc($u['loc']) . "</loc>\n";
    echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    echo '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
