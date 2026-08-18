<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Oscryn\Extensions\Model;

class Widget extends Model
{
    protected const TABLE = 'widgets';

    protected array $casts = [
        'int_col'   => 'int',
        'float_col' => 'float',
        'str_col'   => 'string',
        'json_col'  => 'array',
        'bool_col'  => 'bool',
        'name'      => Casts\Reverse::class,
    ];

    protected array $fillable = [
        'int_col',
        'float_col',
        'str_col',
        'json_col',
        'bool_col',
        'name',
    ];
}
