<?php

namespace Oscryn\Database\Schema;

class Column
{
    public string $name;
    public string $type;
    public bool $nullable = false;
    public bool $unsigned = false;
    public bool $autoIncrement = false;
    public bool $primary = false;
    public bool $unique = false;
    public bool $hasDefault = false;
    public mixed $default = null;
    public ?string $after = null;

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;

        return $this;
    }

    public function unsigned(bool $value = true): static
    {
        $this->unsigned = $value;

        return $this;
    }

    public function autoIncrement(bool $value = true): static
    {
        $this->autoIncrement = $value;

        return $this;
    }

    public function primary(bool $value = true): static
    {
        $this->primary = $value;

        return $this;
    }

    public function unique(bool $value = true): static
    {
        $this->unique = $value;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;

        return $this;
    }

    public function after(string $column): static
    {
        $this->after = $column;

        return $this;
    }

    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";

        if ($this->unsigned) {
            $sql .= ' UNSIGNED';
        }

        if (!$this->nullable && !$this->autoIncrement) {
            $sql .= ' NOT NULL';
        }

        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($this->hasDefault) {
            $sql .= ' DEFAULT '.$this->quoteDefault($this->default);
        }

        if ($this->primary) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }

    protected function quoteDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace("'", "''", (string) $value)."'";
    }
}
