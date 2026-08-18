<?php

namespace Oscryn\Extensions;

use JsonSerializable;
use Oscryn\Database\Casts\CastsAttributes;
use Oscryn\Database\DBConnector;
use Oscryn\Database\Paginator;
use Oscryn\Database\QueryBuilder;
use Oscryn\Database\Relations\BelongsTo;
use Oscryn\Database\Relations\HasMany;
use Oscryn\Exceptions\ModelNotFoundException;
use ReflectionClass;
use RuntimeException;

abstract class Model extends DBConnector implements JsonSerializable
{
    protected const TABLE = '';
    protected const TIMESTAMPS = true;
    protected const SOFT_DELETES = false;

    protected array $attributes = [];

    protected array $original = [];

    protected array $casts = [];

    protected array $fillable = [];

    protected array $relations = [];

    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function table(): string
    {
        if (static::TABLE !== '') {
            return static::TABLE;
        }

        return strtolower((new ReflectionClass(static::class))->getShortName()).'s';
    }

    public static function usesTimestamps(): bool
    {
        return static::TIMESTAMPS;
    }

    public static function softDeletes(): bool
    {
        return static::SOFT_DELETES;
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class, static::table());
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    public static function findOrFail(mixed $id): static
    {
        return static::query()->findOrFail($id);
    }

    public static function firstOrFail(): static
    {
        $model = static::query()->first();

        if ($model === null) {
            throw new ModelNotFoundException(static::class);
        }

        return $model;
    }

    public static function firstWhere(array $wheres): ?static
    {
        return static::query()->firstWhere($wheres);
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        if (($model = static::firstWhere($attributes)) !== null) {
            return $model;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        if (($model = static::firstWhere($attributes)) !== null) {
            $model->fill($values)->save();

            return $model;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    public static function with(...$relations): QueryBuilder
    {
        return static::query()->with(...$relations);
    }

    public static function paginate(int $perPage = 15, ?int $page = null): Paginator
    {
        return static::query()->paginate($perPage, $page);
    }

    public static function fromRow(array $row): static
    {
        $model = (new static)->forceFill($row);
        $model->syncOriginal();
        $model->exists = true;

        return $model;
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::query()->{$method}(...$parameters);
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->fillable !== [] && !in_array($key, $this->fillable, true)) {
                throw new RuntimeException(sprintf(
                    'Add "%s" to the $fillable property of %s to allow mass assignment, or use forceFill().',
                    $key,
                    static::class
                ));
            }

            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function forceFill(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($this->hasCast($key)) {
            $value = $this->castAttributeForSet($key, $value);
        }

        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        if ($value === null || !$this->hasCast($key)) {
            return $value;
        }

        return $this->castAttributeForGet($key, $value);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? $default;
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return array_key_exists($key, $this->attributes)
                && (!array_key_exists($key, $this->original) || $this->attributes[$key] !== $this->original[$key]);
        }

        return $this->getDirty() !== [];
    }

    public function isClean(?string $key = null): bool
    {
        return !$this->isDirty($key);
    }

    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    public function getCasts(): array
    {
        return $this->casts;
    }

    public function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function save(): bool
    {
        return $this->exists ? $this->performUpdate() : $this->performInsert();
    }

    public function update(array $attributes): bool
    {
        if (!$this->exists) {
            $this->fill($attributes);

            return $this->save();
        }

        $this->fill($attributes);

        return $this->performUpdate();
    }

    public function delete(): bool
    {
        if (static::softDeletes()) {
            return $this->performSoftDelete();
        }

        return $this->performDelete();
    }

    public function forceDelete(): bool
    {
        if (!$this->exists || $this->getKey() === null) {
            return false;
        }

        $deleted = static::query()->where('id', $this->getKey())->withTrashed()->delete() > 0;

        if ($deleted) {
            $this->exists = false;
        }

        return $deleted;
    }

    public function restore(): bool
    {
        if (!$this->exists || $this->getKey() === null) {
            return false;
        }

        static::query()->where('id', $this->getKey())->withTrashed()->update(['deleted_at' => null]);
        $this->attributes['deleted_at'] = null;

        return true;
    }

    public function trashed(): bool
    {
        return $this->getAttribute('deleted_at') !== null;
    }

    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        return new HasMany($this, $related, $foreignKey ?? static::foreignKeyFrom(static::class), $localKey ?? 'id');
    }

    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        return new BelongsTo($this, $related, $foreignKey ?? static::foreignKeyFrom($related), $ownerKey ?? 'id');
    }

    public static function foreignKeyFrom(string $related): string
    {
        return strtolower((new ReflectionClass($related))->getShortName()).'_id';
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    public function hasRelation(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function toArray(): array
    {
        $values = [];

        foreach ($this->attributes as $key => $value) {
            $values[$key] = $this->getAttribute($key);
        }

        return $values;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    protected function castAttributeForSet(string $key, mixed $value): mixed
    {
        $cast = $this->casts[$key];

        if (is_subclass_of($cast, CastsAttributes::class)) {
            return (new $cast())->set($this, $key, $value, $this->attributes);
        }

        return match ($cast) {
            'int', 'integer'  => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string'          => (string) $value,
            'array', 'json'   => $this->asJson($value),
            'hashed'          => $value === null ? null : password_hash($value, PASSWORD_DEFAULT),
            default           => $value,
        };
    }

    protected function castAttributeForGet(string $key, mixed $value): mixed
    {
        $cast = $this->casts[$key];

        if (is_subclass_of($cast, CastsAttributes::class)) {
            return (new $cast())->get($this, $key, $value, $this->attributes);
        }

        return match ($cast) {
            'int', 'integer'  => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string'          => (string) $value,
            'array', 'json'   => $this->fromJson($value),
            default           => $value,
        };
    }

    protected function performInsert(): bool
    {
        if (static::usesTimestamps()) {
            $now = date('Y-m-d H:i:s');
            $this->attributes['created_at'] ??= $now;
            $this->attributes['updated_at'] ??= $now;
        }

        $id = static::query()->insert($this->attributes);
        $this->attributes['id'] = $id;
        $this->exists = true;
        $this->syncOriginal();

        return true;
    }

    protected function performUpdate(): bool
    {
        if ($this->getKey() === null) {
            throw new RuntimeException('Cannot update a model without a primary key.');
        }

        if (static::usesTimestamps()) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $dirty = $this->getDirty();

        if ($dirty === []) {
            return true;
        }

        static::query()->where('id', $this->getKey())->update($dirty);
        $this->syncOriginal();

        return true;
    }

    protected function performDelete(): bool
    {
        if (!$this->exists || $this->getKey() === null) {
            return false;
        }

        $deleted = static::query()->where('id', $this->getKey())->delete() > 0;

        if ($deleted) {
            $this->exists = false;
        }

        return $deleted;
    }

    protected function performSoftDelete(): bool
    {
        if (!$this->exists || $this->getKey() === null) {
            return false;
        }

        $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
        $this->performUpdate();

        return true;
    }

    private function asJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    private function fromJson(string $value): mixed
    {
        return json_decode($value, true);
    }

    public function __get(string $key): mixed
    {
        if ($this->hasRelation($key)) {
            return $this->getRelation($key);
        }

        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}
