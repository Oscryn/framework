<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Oscryn\Database\Relations\HasMany;
use Oscryn\Extensions\Model;

class User extends Model
{
    protected const TABLE = 'users';

    protected array $casts = [
        'password' => 'hashed',
    ];

    protected array $fillable = [
        'name',
        'email',
        'password',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
