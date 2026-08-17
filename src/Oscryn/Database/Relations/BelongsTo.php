<?php

namespace Oscryn\Database\Relations;

use Oscryn\Database\QueryBuilder;
use Oscryn\Extensions\Model;

class BelongsTo
{
    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $ownerKey = 'id',
    ) {
    }

    public function query(): QueryBuilder
    {
        return $this->related::query()
            ->where($this->ownerKey, $this->parent->getAttribute($this->foreignKey));
    }

    public function get(): ?Model
    {
        return $this->query()->first();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $foreignKey = $this->foreignKey;

        $keys = array_values(array_unique(array_filter(array_map(
            static fn (Model $model) => $model->getAttribute($foreignKey),
            $models
        ))));

        if ($keys === []) {
            $this->setEmpty($models, $name);

            return;
        }

        $grouped = [];

        foreach ($this->related::query()->whereIn($this->ownerKey, $keys)->get() as $related) {
            $grouped[$related->getAttribute($this->ownerKey)] = $related;
        }

        foreach ($models as $model) {
            $model->setRelation($name, $grouped[$model->getAttribute($this->foreignKey)] ?? null);
        }
    }

    protected function setEmpty(array $models, string $name): void
    {
        foreach ($models as $model) {
            $model->setRelation($name, null);
        }
    }
}
