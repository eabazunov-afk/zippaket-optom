<?php
use PHPUnit\Framework\TestCase;

// Задаём базовый URL до подключения, чтобы не тянуть config.php.
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://zippaket-optom.ru/');
}
require_once __DIR__ . '/../includes/seo.php';

class SeoTest extends TestCase
{
    public function testBaseStripsTrailingSlash(): void
    {
        $this->assertSame('https://zippaket-optom.ru', seo_base());
    }

    public function testUrlJoins(): void
    {
        $this->assertSame('https://zippaket-optom.ru/product/1', seo_url('/product/1'));
        $this->assertSame('https://zippaket-optom.ru/product/1', seo_url('product/1'));
    }

    public function testBreadcrumbJsonldStructure(): void
    {
        $json = seo_breadcrumb_jsonld([
            ['name' => 'Главная', 'url' => '/'],
            ['name' => 'Каталог', 'url' => '/katalog_zip_paketov/'],
            ['name' => 'Пакет 20x15', 'url' => '/product/1'],
        ]);
        $data = json_decode($json, true);

        $this->assertSame('BreadcrumbList', $data['@type']);
        $this->assertCount(3, $data['itemListElement']);
        $this->assertSame(1, $data['itemListElement'][0]['position']);
        $this->assertSame('Главная', $data['itemListElement'][0]['name']);
        $this->assertSame('https://zippaket-optom.ru/', $data['itemListElement'][0]['item']);
        $this->assertSame(3, $data['itemListElement'][2]['position']);
        $this->assertSame('https://zippaket-optom.ru/product/1', $data['itemListElement'][2]['item']);
    }
}
