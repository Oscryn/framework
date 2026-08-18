<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Oscryn\Extensions\Model;

class Note extends Model
{
    protected const TABLE = 'notes';

    protected const SOFT_DELETES = true;

    protected array $fillable = [
        'body',
    ];
}
