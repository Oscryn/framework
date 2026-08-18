<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use Oscryn\Exceptions\HttpException;
use Oscryn\Exceptions\ModelNotFoundException;
use Oscryn\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class HttpExceptionTest extends TestCase
{
    public function test_status_text(): void
    {
        $this->assertSame('Not Found', HttpException::statusText(404));
        $this->assertSame('Unauthorized', HttpException::statusText(401));
        $this->assertSame('Page Expired', HttpException::statusText(419));
        $this->assertSame('Unprocessable Entity', HttpException::statusText(422));
        $this->assertSame('Error', HttpException::statusText(999));
    }

    public function test_status_message_and_hint(): void
    {
        $exception = new HttpException(404, 'Gone', 'a helpful hint');

        $this->assertSame(404, $exception->status());
        $this->assertSame('Gone', $exception->getMessage());
        $this->assertSame('a helpful hint', $exception->hint());
    }

    public function test_default_message_from_status(): void
    {
        $exception = new HttpException(403);

        $this->assertSame('Forbidden', $exception->getMessage());
        $this->assertSame('', $exception->hint());
    }

    public function test_model_not_found_exception(): void
    {
        $exception = new ModelNotFoundException('App\Models\User', [5]);

        $this->assertSame(404, $exception->status());
        $this->assertStringContainsString('App\Models\User', $exception->getMessage());
        $this->assertStringContainsString('5', $exception->getMessage());
    }

    public function test_validation_exception_carries_errors(): void
    {
        $exception = new ValidationException(['name' => ['The name field is required.']]);

        $this->assertSame(422, $exception->status());
        $this->assertSame(['name' => ['The name field is required.']], $exception->errors());
    }
}
