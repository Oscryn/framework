<?php

namespace Oscryn\Exceptions;

class ValidationException extends HttpException
{
    protected array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;

        parent::__construct(422, 'The given data was invalid.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
