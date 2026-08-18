<?php

declare(strict_types=1);

namespace Tests\Fixtures\Casts;

use Oscryn\Database\Casts\CastsAttributes;
use Oscryn\Extensions\Model;

class Reverse implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : strrev($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : strrev($value);
    }
}
