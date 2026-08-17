<?php

namespace Oscryn\Extensions;

use Oscryn\Database\Casts\CastsAttributes;
use Oscryn\Database\DBConnector;
use Oscryn\Database\QueryBuilder;
use ReflectionClass;

abstract class Model extends DBConnector
{
    protected const TABLE = '';

    protected array $attributes = [];

    protected array $casts = [];

    protected array $fillable = [];

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

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class, static::table());
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    public static function fromRow(array $row): static
    {
        return (new static)->forceFill($row);
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->fillable !== [] && !in_array($key, $this->fillable, true)) {
                continue;
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

    public function getCasts(): array
    {
        return $this->casts;
    }

    public function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
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
