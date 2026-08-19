<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/reviews.php';

class ReviewsTest extends TestCase
{
    public function testClampRating(): void
    {
        $this->assertSame(1, review_clamp_rating(0));
        $this->assertSame(5, review_clamp_rating(9));
        $this->assertSame(4, review_clamp_rating(4));
    }

    public function testStars(): void
    {
        $this->assertSame('★★★★★', review_stars(5));
        $this->assertSame('★★★★☆', review_stars(4));
        $this->assertSame('★☆☆☆☆', review_stars(0)); // clamp → 1
    }

    public function testValidateOk(): void
    {
        $r = review_validate(['author_name' => 'Иван', 'body' => 'Отличная упаковка, брали оптом.', 'rating' => '7']);
        $this->assertTrue($r['ok']);
        $this->assertSame(5, $r['data']['rating']);        // clamp
        $this->assertSame('Иван', $r['data']['author_name']);
    }

    public function testValidateRejectsShort(): void
    {
        $r = review_validate(['author_name' => '', 'body' => 'мало']);
        $this->assertFalse($r['ok']);
        $this->assertArrayHasKey('author_name', $r['errors']);
        $this->assertArrayHasKey('body', $r['errors']);
    }
}
