<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/faq.php';

class FaqTest extends TestCase
{
    public function testJsonldStructure(): void
    {
        $out = faq_jsonld([['q' => 'Мин. партия?', 'a' => 'От 1000 шт.']]);
        $this->assertStringContainsString('application/ld+json', $out);
        $json = json_decode(preg_replace('#</?script[^>]*>#', '', $out), true);
        $this->assertSame('FAQPage', $json['@type']);
        $this->assertSame('Question', $json['mainEntity'][0]['@type']);
        $this->assertSame('Мин. партия?', $json['mainEntity'][0]['name']);
        $this->assertSame('От 1000 шт.', $json['mainEntity'][0]['acceptedAnswer']['text']);
    }
}
