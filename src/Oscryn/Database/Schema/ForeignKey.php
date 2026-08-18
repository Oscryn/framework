<?php

namespace Oscryn\Database\Schema;

class ForeignKey
{
    protected ?string $references = null;
    protected ?string $on = null;
    protected ?string $onDelete = null;
    protected ?string $onUpdate = null;

    public function __construct(
        protected string $table,
        protected string $column,
    ) {
    }

    public function references(string $column): static
    {
        $this->references = $column;

        return $this;
    }

    public function on(string $table): static
    {
        $this->on = $table;

        return $this;
    }

    public function constrained(?string $table = null, ?string $column = 'id'): static
    {
        $this->on = $table ?? $this->on ?? $this->guessTable();
        $this->references = $column ?? $this->references ?? 'id';

        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);

        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);

        return $this;
    }

    public function cascadeOnDelete(): static
    {
        return $this->onDelete('cascade');
    }

    public function nullOnDelete(): static
    {
        return $this->onDelete('set null');
    }

    public function toSql(): string
    {
        $on = $this->on ?? $this->guessTable();
        $references = $this->references ?? 'id';

        $sql = 'CONSTRAINT `'.$this->name().'` FOREIGN KEY (`'.$this->column.'`) '
            .'REFERENCES `'.$on.'` (`'.$references.'`)';

        if ($this->onDelete !== null) {
            $sql .= ' ON DELETE '.$this->onDelete;
        }

        if ($this->onUpdate !== null) {
            $sql .= ' ON UPDATE '.$this->onUpdate;
        }

        return $sql;
    }

    public function name(): string
    {
        return $this->table.'_'.$this->column.'_foreign';
    }

    protected function guessTable(): string
    {
        $column = preg_replace('/_id$/', '', $this->column) ?? $this->column;

        return $column.'s';
    }
}
