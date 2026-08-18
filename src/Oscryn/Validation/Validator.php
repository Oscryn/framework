<?php

namespace Oscryn\Validation;

use Oscryn\Exceptions\ValidationException;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $errors = [];
    protected bool $validated = false;

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): static
    {
        return new static($data, $rules);
    }

    public function validate(): array
    {
        $this->run();

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }

        return $this->validated();
    }

    public function fails(): bool
    {
        $this->run();

        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    public function validated(): array
    {
        return array_intersect_key($this->data, array_flip(array_keys($this->rules)));
    }

    protected function run(): void
    {
        if ($this->validated) {
            return;
        }

        $this->validated = true;
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            foreach ($this->parseRules($rules) as $rule) {
                [$name, $parameters] = $this->parseRule($rule);
                $method = 'validate'.ucfirst($name);

                if (!method_exists($this, $method)) {
                    throw new \InvalidArgumentException("Validation rule [{$name}] is not supported.");
                }

                $this->{$method}($field, $parameters);
            }
        }
    }

    protected function parseRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return $rules === '' ? [] : explode('|', $rules);
        }

        if (is_array($rules)) {
            return $rules;
        }

        return [];
    }

    protected function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            return explode(':', $rule, 2);
        }

        return [$rule, ''];
    }

    protected function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    protected function hasValue(string $field): bool
    {
        if (!array_key_exists($field, $this->data)) {
            return false;
        }

        $value = $this->data[$field];

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    protected function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    protected function validateRequired(string $field, string $parameters): void
    {
        if (!$this->hasValue($field)) {
            $this->addError($field, 'The '.$this->label($field).' field is required.');
        }
    }

    protected function validateNullable(string $field, string $parameters): void
    {
        // Handled by skipping the remaining rules when the value is null.
    }

    protected function validateString(string $field, string $parameters): void
    {
        $value = $this->value($field);

        if ($value === null || is_string($value)) {
            return;
        }

        $this->addError($field, 'The '.$this->label($field).' field must be a string.');
    }

    protected function validateInteger(string $field, string $parameters): void
    {
        $value = $this->value($field);

        if ($value === null || !is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return;
        }

        $this->addError($field, 'The '.$this->label($field).' field must be an integer.');
    }

    protected function validateNumeric(string $field, string $parameters): void
    {
        $value = $this->value($field);

        if ($value === null || !is_scalar($value) || is_numeric($value)) {
            return;
        }

        $this->addError($field, 'The '.$this->label($field).' field must be numeric.');
    }

    protected function validateBoolean(string $field, string $parameters): void
    {
        $value = $this->value($field);

        if ($value === null || in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            return;
        }

        $this->addError($field, 'The '.$this->label($field).' field must be true or false.');
    }

    protected function validateEmail(string $field, string $parameters): void
    {
        $value = $this->value($field);

        if ($value === null || !is_scalar($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return;
        }

        $this->addError($field, 'The '.$this->label($field).' field must be a valid email address.');
    }

    protected function validateMin(string $field, string $parameters): void
    {
        $value = $this->value($field);
        $length = $this->sizeOf($value);

        if ($length !== null && (float) $length < (float) $parameters) {
            $this->addError($field, 'The '.$this->label($field).' field must be at least '.$parameters.'.');
        }
    }

    protected function validateMax(string $field, string $parameters): void
    {
        $value = $this->value($field);
        $length = $this->sizeOf($value);

        if ($length !== null && (float) $length > (float) $parameters) {
            $this->addError($field, 'The '.$this->label($field).' field must not exceed '.$parameters.'.');
        }
    }

    protected function validateIn(string $field, string $parameters): void
    {
        $value = $this->value($field);

        if ($value === null) {
            return;
        }

        $allowed = explode(',', $parameters);

        if (!in_array($value, $allowed, true) && !in_array((string) $value, $allowed, true)) {
            $this->addError($field, 'The selected '.$this->label($field).' is invalid.');
        }
    }

    protected function label(string $field): string
    {
        return str_replace('_', ' ', $field);
    }

    protected function sizeOf(mixed $value): int|float|null
    {
        return match (true) {
            $value === null     => null,
            is_string($value)   => mb_strlen($value),
            is_array($value)    => count($value),
            is_int($value), is_float($value) => $value,
            default             => null,
        };
    }
}
