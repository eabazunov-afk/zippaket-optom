<?php
// Зависит только от константы SITE_URL (определяется в config.php, который к моменту
// использования уже подключён вызывающей страницей). Сам config не тянем — чтобы
// хелперы оставались чисто тестируемыми.

/**
 * Флаги json_encode для любых данных, попадающих внутрь <script type="application/ld+json">.
 * HEX_TAG/HEX_AMP/HEX_APOS/HEX_QUOT не дают данным разорвать тег <script> (XSS),
 * UNESCAPED_UNICODE сохраняет кириллицу читаемой для поисковых роботов.
 */
if (!defined('SEO_JSONLD_FLAGS')) {
    define('SEO_JSONLD_FLAGS', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** Канонический базовый URL без хвостового слэша. */
function seo_base(): string
{
    $base = defined('SITE_URL') ? SITE_URL : 'https://zippaket-optom.ru/';
    return rtrim($base, '/');
}

/** Абсолютный URL от пути ('/product/1'). */
function seo_url(string $path): string
{
    return seo_base() . '/' . ltrim($path, '/');
}

/**
 * JSON-LD BreadcrumbList.
 * @param array<int,array{name:string,url:string}> $items позиции крошек по порядку
 * @return string JSON для <script type="application/ld+json">
 */
function seo_breadcrumb_jsonld(array $items): string
{
    $elements = [];
    $pos = 1;
    foreach ($items as $it) {
        $elements[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => (string)($it['name'] ?? ''),
            'item' => seo_url((string)($it['url'] ?? '')),
        ];
    }
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
    return json_encode($ld, SEO_JSONLD_FLAGS | JSON_PRETTY_PRINT);
}
