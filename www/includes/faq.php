<?php
require_once __DIR__ . '/seo.php'; // SEO_JSONLD_FLAGS

/** FAQPage JSON-LD из пар ['q'=>..., 'a'=>...]. Чистая, без вывода в буфер. */
function faq_jsonld(array $items): string {
    $entities = [];
    foreach ($items as $it) {
        $entities[] = [
            '@type' => 'Question',
            'name' => (string)($it['q'] ?? ''),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string)($it['a'] ?? '')],
        ];
    }
    $data = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities];
    return '<script type="application/ld+json">' . json_encode($data, SEO_JSONLD_FLAGS) . '</script>';
}
