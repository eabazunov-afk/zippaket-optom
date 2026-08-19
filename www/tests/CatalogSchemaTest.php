<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/catalog_schema.php';

class CatalogSchemaTest extends TestCase
{
    public function testItemListStructure(): void
    {
        $out = catalog_itemlist_jsonld([
            ['id' => 42, 'full_name' => 'Zip 25x30'],
            ['id' => 7,  'full_name' => 'Слайдер 30x40'],
        ]);
        $json = json_decode(preg_replace('#</?script[^>]*>#', '', $out), true);
        $this->assertSame('ItemList', $json['@type']);
        $this->assertSame(1, $json['itemListElement'][0]['position']);
        $this->assertStringContainsString('/product/42', $json['itemListElement'][0]['url']);
        $this->assertSame('Zip 25x30', $json['itemListElement'][0]['name']);
        $this->assertSame(2, $json['itemListElement'][1]['position']);
    }
}
