<?php

declare(strict_types=1);

namespace Tests\Http;

use Oscryn\Http\Csrf;
use Oscryn\Http\Request;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
        Csrf::$except = [];
    }

    protected function tearDown(): void
    {
        Csrf::$except = [];

        parent::tearDown();
    }

    public function test_token_is_generated_and_stable(): void
    {
        $first = Csrf::token();
        $second = Csrf::token();

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
    }

    public function test_field_contains_token(): void
    {
        $this->assertStringContainsString('name="_token"', Csrf::field());
        $this->assertStringContainsString(Csrf::token(), Csrf::field());
    }

    public function test_validate_succeeds_with_matching_token(): void
    {
        $_POST['_token'] = Csrf::token();

        $this->assertTrue(Csrf::validate(new Request()));
    }

    public function test_validate_succeeds_with_header_token(): void
    {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();

        $this->assertTrue(Csrf::validate(new Request()));
    }

    public function test_validate_fails_with_wrong_token(): void
    {
        $_POST['_token'] = 'wrong';

        $this->assertFalse(Csrf::validate(new Request()));
    }

    public function test_validate_fails_without_token(): void
    {
        $this->assertFalse(Csrf::validate(new Request()));
    }

    public function test_except_matches_wildcards(): void
    {
        Csrf::except('/api/*');

        $this->assertTrue(Csrf::isExcept('/api/users'));
        $this->assertFalse(Csrf::isExcept('/dashboard'));
    }
}
