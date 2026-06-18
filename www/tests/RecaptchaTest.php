<?php
use PHPUnit\Framework\TestCase;

if (!defined('RECAPTCHA_SECRET_KEY')) {
    define('RECAPTCHA_SECRET_KEY', 'test-secret');
}
require_once __DIR__ . '/../includes/recaptcha.php';

class RecaptchaTest extends TestCase
{
    public function testSuccessHighScorePasses(): void
    {
        $json = json_encode(['success' => true, 'score' => 0.9, 'action' => 'checkout']);
        $this->assertTrue(recaptcha_evaluate($json, 'checkout', 0.5));
    }

    public function testFailureRejected(): void
    {
        $this->assertFalse(recaptcha_evaluate('{"success":false}', 'checkout'));
    }

    public function testLowScoreRejected(): void
    {
        $json = json_encode(['success' => true, 'score' => 0.2]);
        $this->assertFalse(recaptcha_evaluate($json, 'checkout', 0.5));
    }

    public function testActionMismatchRejected(): void
    {
        $json = json_encode(['success' => true, 'score' => 0.9, 'action' => 'login']);
        $this->assertFalse(recaptcha_evaluate($json, 'checkout', 0.5));
    }

    public function testInvalidJsonDoesNotBlock(): void
    {
        // невалидный ответ Google не должен ронять оформление
        $this->assertTrue(recaptcha_evaluate('not json', 'checkout'));
    }

    public function testNoScoreFieldPasses(): void
    {
        $this->assertTrue(recaptcha_evaluate('{"success":true,"action":"checkout"}', 'checkout', 0.5));
    }
}
