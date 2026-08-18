<?php

namespace Oscryn\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    protected string $hint;

    public function __construct(int $status, string $message = '', string $hint = '')
    {
        $this->hint = $hint;

        parent::__construct($message ?: static::statusText($status), $status);
    }

    public function status(): int
    {
        return $this->getCode();
    }

    public function hint(): string
    {
        return $this->hint;
    }

    public static function statusText(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
