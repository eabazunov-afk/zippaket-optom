<?php
use PHPUnit\Framework\TestCase;

if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://zippaket-optom.ru/');
}
require_once __DIR__ . '/../includes/seo.php';
require_once __DIR__ . '/../includes/catalog_schema.php';
require_once __DIR__ . '/../includes/faq.php';

/**
 * Регрессия на XSS через <script type="application/ld+json">.
 * Данные из $_GET/БД попадают в JSON-LD; без JSON_HEX_TAG строка `</script>`
 * закрывала тег и выполняла произвольный JS (см. /katalog_zip_paketov/?category=...).
 * Кириллица при этом обязана остаться читаемой (JSON_UNESCAPED_UNICODE).
 */
class JsonLdEscapingTest extends TestCase
{
    private const PAYLOAD = '</script><script>alert(1)</script>';
    private const CYRILLIC = 'ZIP-пакеты с замком «слайдер»';

    /** Внутри JSON-полезной нагрузки не должно остаться ни одного литерального `<script`/`</script`. */
    private function assertNoTagBreakout(string $jsonBody, string $context): void
    {
        $this->assertSame(
            0,
            preg_match('#<\s*/?\s*script#i', $jsonBody),
            "Выход из тега <script> в $context: $jsonBody"
        );
        // Угловые скобки должны быть представлены как < / >
        $this->assertStringNotContainsString('<', $jsonBody, "Незаэкранированный '<' в $context");
        $this->assertStringNotContainsString('>', $jsonBody, "Незаэкранированный '>' в $context");
    }

    /** Кириллица не должна превращаться в \uXXXX. */
    private function assertCyrillicReadable(string $jsonBody, string $context): void
    {
        $this->assertStringContainsString(self::CYRILLIC, $jsonBody, "Кириллица искажена в $context");
        $this->assertSame(
            0,
            preg_match('/\\\\u0[45][0-9a-f]{2}/i', $jsonBody),
            "Кириллица ушла в \\uXXXX в $context: $jsonBody"
        );
    }

    private function stripScriptTags(string $out): string
    {
        return preg_replace('#</?script[^>]*>#i', '', $out);
    }

    public function testBreadcrumbEscapesScriptTag(): void
    {
        $json = seo_breadcrumb_jsonld([
            ['name' => self::PAYLOAD, 'url' => '/katalog_zip_paketov/?category=' . self::PAYLOAD],
            ['name' => self::CYRILLIC, 'url' => '/product/1'],
        ]);

        $this->assertNoTagBreakout($json, 'seo_breadcrumb_jsonld');
        $this->assertCyrillicReadable($json, 'seo_breadcrumb_jsonld');

        // Данные при этом не потеряны — после json_decode payload восстанавливается как есть.
        $data = json_decode($json, true);
        $this->assertNotNull($data, 'JSON-LD крошек невалиден');
        $this->assertSame(self::PAYLOAD, $data['itemListElement'][0]['name']);
        $this->assertSame(self::CYRILLIC, $data['itemListElement'][1]['name']);
    }

    public function testCatalogItemListEscapesScriptTag(): void
    {
        $out = catalog_itemlist_jsonld([
            ['id' => 42, 'full_name' => self::PAYLOAD],
            ['id' => 7,  'full_name' => self::CYRILLIC],
        ]);

        // Ровно один открывающий и один закрывающий тег — обёртка функции.
        $this->assertSame(1, preg_match_all('#<script type="application/ld\+json">#', $out));
        $this->assertSame(1, preg_match_all('#</script>#', $out));

        $body = $this->stripScriptTags($out);
        $this->assertNoTagBreakout($body, 'catalog_itemlist_jsonld');
        $this->assertCyrillicReadable($body, 'catalog_itemlist_jsonld');

        $data = json_decode($body, true);
        $this->assertNotNull($data, 'JSON-LD каталога невалиден');
        $this->assertSame(self::PAYLOAD, $data['itemListElement'][0]['name']);
        $this->assertSame(self::CYRILLIC, $data['itemListElement'][1]['name']);
    }

    public function testFaqEscapesScriptTag(): void
    {
        $out = faq_jsonld([
            ['q' => self::PAYLOAD, 'a' => self::PAYLOAD],
            ['q' => self::CYRILLIC, 'a' => self::CYRILLIC],
        ]);

        $this->assertSame(1, preg_match_all('#</script>#', $out));

        $body = $this->stripScriptTags($out);
        $this->assertNoTagBreakout($body, 'faq_jsonld');
        $this->assertCyrillicReadable($body, 'faq_jsonld');

        $data = json_decode($body, true);
        $this->assertNotNull($data, 'JSON-LD FAQ невалиден');
        $this->assertSame(self::PAYLOAD, $data['mainEntity'][0]['acceptedAnswer']['text']);
        $this->assertSame(self::CYRILLIC, $data['mainEntity'][1]['name']);
    }

    /**
     * product.php и index.php вызывают json_encode напрямую — они обязаны
     * использовать те же флаги. Проверяем сам набор флагов.
     */
    public function testSharedFlagsCoverAllInjectionChars(): void
    {
        $this->assertTrue(defined('SEO_JSONLD_FLAGS'));
        foreach ([
            'JSON_HEX_TAG' => JSON_HEX_TAG,
            'JSON_HEX_AMP' => JSON_HEX_AMP,
            'JSON_HEX_APOS' => JSON_HEX_APOS,
            'JSON_HEX_QUOT' => JSON_HEX_QUOT,
            'JSON_UNESCAPED_UNICODE' => JSON_UNESCAPED_UNICODE,
        ] as $name => $flag) {
            $this->assertSame($flag, SEO_JSONLD_FLAGS & $flag, "SEO_JSONLD_FLAGS не содержит $name");
        }

        $json = json_encode(['name' => self::PAYLOAD . '&\'"', 'ru' => self::CYRILLIC], SEO_JSONLD_FLAGS);
        $this->assertNoTagBreakout($json, 'SEO_JSONLD_FLAGS');
        $this->assertCyrillicReadable($json, 'SEO_JSONLD_FLAGS');
        $this->assertStringNotContainsString('&', $json);
        $this->assertStringNotContainsString("'", $json);
    }
}
