<?php

namespace Oscryn\Http;

use Oscryn\Exceptions\HttpException;
use Oscryn\Exceptions\ValidationException;
use Oscryn\Validation\Validator;

abstract class FormRequest extends Request
{
    protected ?Validator $validator = null;

    abstract public function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    public function validateResolved(): void
    {
        if (!$this->authorize()) {
            throw new HttpException(403, 'Forbidden', 'This action is unauthorized.');
        }

        $validator = $this->validator();

        if ($validator->fails()) {
            $this->failsValidation(new ValidationException($validator->errors()));
        }
    }

    public function validated(): array
    {
        return $this->validator()->validated();
    }

    public function errors(): array
    {
        return $this->validator()->errors();
    }

    protected function validator(): Validator
    {
        return $this->validator ??= Validator::make($this->all(), $this->rules());
    }

    protected function failsValidation(ValidationException $exception): void
    {
        throw $exception;
    }
}
