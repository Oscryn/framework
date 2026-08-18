<?php

declare(strict_types=1);

namespace Tests\Auth;

use Tests\TestCase;
use Oscryn\Auth\Auth;
use Tests\Fixtures\User;

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Auth::useModel(User::class);
    }

    public function test_attempt_succeeds_with_valid_credentials(): void
    {
        User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret123']);

        $this->assertTrue(Auth::instance()->attempt([
            'email' => 'jane@example.com',
            'password' => 'secret123',
        ]));

        $this->assertTrue(Auth::instance()->check());
        $this->assertNotNull(Auth::instance()->user());
    }

    public function test_attempt_fails_with_wrong_password(): void
    {
        User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret123']);

        $this->assertFalse(Auth::instance()->attempt([
            'email' => 'jane@example.com',
            'password' => 'wrong',
        ]));

        $this->assertTrue(Auth::instance()->guest());
    }

    public function test_login_and_logout(): void
    {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret123']);

        Auth::instance()->login($user);

        $this->assertTrue(Auth::instance()->check());
        $this->assertSame($user->getKey(), Auth::instance()->id());

        Auth::instance()->logout();

        $this->assertTrue(Auth::instance()->guest());
        $this->assertNull(Auth::instance()->user());
    }

    public function test_guest_returns_true_when_not_logged_in(): void
    {
        $this->assertTrue(Auth::instance()->guest());
        $this->assertFalse(Auth::instance()->check());
    }

    public function test_login_using_id(): void
    {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret123']);

        $resolved = Auth::instance()->loginUsingId($user->getKey());

        $this->assertNotNull($resolved);
        $this->assertSame($user->getKey(), $resolved->getKey());
        $this->assertTrue(Auth::instance()->check());
    }

    public function test_login_using_missing_id_returns_null(): void
    {
        $this->assertNull(Auth::instance()->loginUsingId(999));
        $this->assertFalse(Auth::instance()->check());
    }

    public function test_attempt_with_missing_password_fails(): void
    {
        User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret123']);

        $this->assertFalse(Auth::instance()->attempt(['email' => 'jane@example.com']));
        $this->assertTrue(Auth::instance()->guest());
    }
}
