<?php

declare(strict_types=1);

namespace Tests\Http;

use Oscryn\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        $_POST = [];
        $_GET = [];

        parent::tearDown();
    }

    public function test_method_spoofing_from_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_method'] = 'DELETE';

        $this->assertSame('DELETE', (new Request)->method());
    }

    public function test_method_spoofing_ignores_unknown_verbs(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_method'] = 'NOPE';

        $this->assertSame('POST', (new Request)->method());
    }

    public function test_method_returns_get_by_default(): void
    {
        $this->assertSame('GET', (new Request)->method());
    }
}
