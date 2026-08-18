<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Oscryn\Database\Relations\BelongsTo;
use Oscryn\Extensions\Model;

class Post extends Model
{
    protected const TABLE = 'posts';

    protected array $fillable = [
        'user_id',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
