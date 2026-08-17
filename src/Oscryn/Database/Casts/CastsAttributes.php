<?php

namespace Oscryn\Database\Casts;

use Oscryn\Extensions\Model;

interface CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed;

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed;
}
