<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Oscryn\Extensions\Model;

class Todo extends Model
{
    protected const TABLE = 'todos';

    protected array $casts = [
        'completed' => 'bool',
    ];

    protected array $fillable = [
        'title',
        'completed',
    ];
}
