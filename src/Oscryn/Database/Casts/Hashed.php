<?php

namespace Oscryn\Database\Casts;

use Oscryn\Extensions\Model;

class Hashed implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        return password_hash($value, PASSWORD_DEFAULT);
    }
}
