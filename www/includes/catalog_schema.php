<?php
require_once __DIR__ . '/seo.php'; // SEO_JSONLD_FLAGS

/** ItemList JSON-LD из списка товаров каталога. Чистая. */
function catalog_itemlist_jsonld(array $products): string {
    $items = [];
    $pos = 1;
    foreach ($products as $p) {
        $id = (int)($p['id'] ?? 0);
        if ($id <= 0) { continue; }
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'url' => 'https://zippaket-optom.ru/product/' . $id,
            'name' => (string)($p['full_name'] ?? ''),
        ];
    }
    $data = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items];
    return '<script type="application/ld+json">' . json_encode($data, SEO_JSONLD_FLAGS) . '</script>';
}
