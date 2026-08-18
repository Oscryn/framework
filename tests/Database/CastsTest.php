<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use Tests\Fixtures\User;
use Tests\Fixtures\Widget;

final class CastsTest extends TestCase
{
    public function test_scalar_casts_round_trip(): void
    {
        $widget = Widget::create([
            'int_col'   => '42',
            'float_col' => '1.5',
            'str_col'   => 123,
            'bool_col'  => 1,
        ]);

        $widget = Widget::find($widget->getKey());

        $this->assertSame(42, $widget->int_col);
        $this->assertSame(1.5, $widget->float_col);
        $this->assertSame('123', $widget->str_col);
        $this->assertTrue($widget->bool_col);
    }

    public function test_array_cast_round_trips(): void
    {
        $widget = Widget::create(['json_col' => ['a' => 1, 'b' => 2]]);

        $widget = Widget::find($widget->getKey());

        $this->assertSame(['a' => 1, 'b' => 2], $widget->json_col);
    }

    public function test_custom_cast_round_trips(): void
    {
        $widget = Widget::create(['name' => 'abc']);

        $widget = Widget::find($widget->getKey());

        $this->assertSame('abc', $widget->name);
    }

    public function test_hashed_cast_hashes_password(): void
    {
        $user = User::create(['name' => 'Jane', 'email' => 'j@example.com', 'password' => 'secret']);

        $hash = $user->getAttribute('password');

        $this->assertNotSame('secret', $hash);
        $this->assertTrue(password_verify('secret', $hash));
    }

    public function test_to_array_and_json_serialize(): void
    {
        $widget = Widget::create(['name' => 'abc', 'bool_col' => true]);

        $array = $widget->toArray();

        $this->assertSame('abc', $array['name']);
        $this->assertTrue($array['bool_col']);

        $this->assertSame($array, $widget->jsonSerialize());
    }

    public function test_magic_getters_and_isset(): void
    {
        $widget = Widget::create(['name' => 'abc']);

        $this->assertTrue(isset($widget->name));
        $this->assertSame('abc', $widget->name);
        $this->assertFalse(isset($widget->missing));
        $this->assertNull($widget->missing);
    }
}
