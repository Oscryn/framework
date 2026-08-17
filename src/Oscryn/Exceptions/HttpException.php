<?php

namespace Oscryn\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(int $status, string $message = '')
    {
        parent::__construct($message ?: static::statusText($status), $status);
    }

    public function status(): int
    {
        return $this->getCode();
    }

    public static function statusText(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
