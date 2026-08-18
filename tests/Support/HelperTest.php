<?php

declare(strict_types=1);

namespace Tests\Support;

use Oscryn\Auth\Auth;
use Oscryn\Http\Request;
use Oscryn\Http\Response;
use PHPUnit\Framework\TestCase;

final class HelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER = [];
    }

    public function test_env_helper_returns_default(): void
    {
        $this->assertSame('fallback', env('OSCRYN_UNSET_VARIABLE', 'fallback'));
    }

    public function test_app_env_helper(): void
    {
        putenv('APP_ENV=testing');

        $this->assertSame('testing', app_env());
        $this->assertTrue(app_env('testing'));
        $this->assertFalse(app_env('production'));
    }

    public function test_csrf_helpers(): void
    {
        $this->assertIsString(csrf_token());
        $this->assertStringContainsString('name="_token"', csrf_field());
    }

    public function test_redirect_helper(): void
    {
        $response = redirect('/login');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(302, $response->status());
    }

    public function test_auth_helper(): void
    {
        $this->assertInstanceOf(Auth::class, auth());
    }

    public function test_request_helper(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $this->assertInstanceOf(Request::class, request());
        $this->assertSame('GET', request()->method());
    }
}
