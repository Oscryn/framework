<?php

namespace Oscryn\Database\Relations;

use Oscryn\Database\QueryBuilder;
use Oscryn\Extensions\Model;

class HasMany
{
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $localKey = 'id',
    ) {
    }

    public function query(): QueryBuilder
    {
        return $this->related::query()
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey));
    }

    public function get(): array
    {
        return $this->query()->get();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $localKey = $this->localKey;

        $keys = array_values(array_unique(array_filter(array_map(
            static fn (Model $model) => $model->getAttribute($localKey),
            $models
        ))));

        if ($keys === []) {
            $this->setEmpty($models, $name);

            return;
        }

        $grouped = [];

        foreach ($this->related::query()->whereIn($this->foreignKey, $keys)->get() as $related) {
            $grouped[$related->getAttribute($this->foreignKey)][] = $related;
        }

        foreach ($models as $model) {
            $model->setRelation($name, $grouped[$model->getAttribute($this->localKey)] ?? []);
        }
    }

    protected function setEmpty(array $models, string $name): void
    {
        foreach ($models as $model) {
            $model->setRelation($name, []);
        }
    }
}
