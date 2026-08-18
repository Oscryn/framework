<?php

declare(strict_types=1);

namespace Tests\Validation;

use Oscryn\Exceptions\ValidationException;
use Oscryn\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_passes_valid_data(): void
    {
        $validator = Validator::make(
            ['name' => 'Jane', 'email' => 'jane@example.com'],
            ['name' => 'required|min:2|max:50', 'email' => 'required|email']
        );

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->errors());
    }

    public function test_required_fails_on_missing_field(): void
    {
        $validator = Validator::make([], ['name' => 'required']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function test_email_fails_on_invalid_email(): void
    {
        $validator = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);

        $this->assertTrue($validator->fails());
    }

    public function test_min_and_max_length(): void
    {
        $this->assertTrue(Validator::make(['title' => 'ab'], ['title' => 'min:3'])->fails());
        $this->assertTrue(Validator::make(['title' => 'abcdef'], ['title' => 'max:5'])->fails());
        $this->assertTrue(Validator::make(['title' => 'abc'], ['title' => 'min:3|max:5'])->passes());
    }

    public function test_validate_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        Validator::make([], ['name' => 'required'])->validate();
    }

    public function test_validate_returns_only_validated_keys(): void
    {
        $data = Validator::make(
            ['name' => 'Jane', 'extra' => 'ignored'],
            ['name' => 'required']
        )->validate();

        $this->assertSame(['name' => 'Jane'], $data);
    }

    public function test_string_rule(): void
    {
        $this->assertTrue(Validator::make(['name' => 'Jane'], ['name' => 'string'])->passes());
        $this->assertTrue(Validator::make(['name' => 123], ['name' => 'string'])->fails());
    }

    public function test_integer_rule(): void
    {
        $this->assertTrue(Validator::make(['age' => 30], ['age' => 'integer'])->passes());
        $this->assertTrue(Validator::make(['age' => '30'], ['age' => 'integer'])->passes());
        $this->assertTrue(Validator::make(['age' => 'abc'], ['age' => 'integer'])->fails());
    }

    public function test_numeric_rule(): void
    {
        $this->assertTrue(Validator::make(['price' => '9.99'], ['price' => 'numeric'])->passes());
        $this->assertTrue(Validator::make(['price' => 'abc'], ['price' => 'numeric'])->fails());
    }

    public function test_boolean_rule(): void
    {
        $this->assertTrue(Validator::make(['active' => 1], ['active' => 'boolean'])->passes());
        $this->assertTrue(Validator::make(['active' => 'true'], ['active' => 'boolean'])->fails());
    }

    public function test_in_rule(): void
    {
        $this->assertTrue(Validator::make(['status' => 'active'], ['status' => 'in:active,pending'])->passes());
        $this->assertTrue(Validator::make(['status' => 'banned'], ['status' => 'in:active,pending'])->fails());
    }

    public function test_min_max_on_array(): void
    {
        $this->assertTrue(Validator::make(['tags' => ['a', 'b']], ['tags' => 'min:2'])->passes());
        $this->assertTrue(Validator::make(['tags' => ['a']], ['tags' => 'min:2'])->fails());
    }

    public function test_unknown_rule_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Validator::make(['name' => 'Jane'], ['name' => 'not_a_rule'])->passes();
    }
}
