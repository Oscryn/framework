<?php

declare(strict_types=1);

namespace Tests\Http;

use Oscryn\Http\Session;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        $this->resetSwept();
    }

    public function test_put_get_has_forget(): void
    {
        Session::put('foo', 'bar');

        $this->assertTrue(Session::has('foo'));
        $this->assertSame('bar', Session::get('foo'));
        $this->assertNull(Session::get('missing'));
        $this->assertSame('default', Session::get('missing', 'default'));

        Session::forget('foo');

        $this->assertFalse(Session::has('foo'));
    }

    public function test_flash_available_on_next_request(): void
    {
        Session::flash('status', 'saved');

        $this->assertFalse(Session::hasFlash('status'));

        $this->resetSwept();

        $this->assertTrue(Session::hasFlash('status'));
        $this->assertSame('saved', Session::getFlash('status'));
    }

    public function test_pull_flash_removes_value(): void
    {
        Session::flash('status', 'saved');

        $this->resetSwept();

        $this->assertSame('saved', Session::pullFlash('status'));
        $this->assertFalse(Session::hasFlash('status'));
    }

    private function resetSwept(): void
    {
        $property = new ReflectionProperty(Session::class, 'swept');
        $property->setValue(null, false);
    }
}
