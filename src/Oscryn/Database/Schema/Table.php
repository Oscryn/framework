<?php

namespace Oscryn\Database\Schema;

class Table
{
    protected string $name;
    protected array $columns = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function id(): Column
    {
        return $this->bigIncrements('id');
    }

    public function bigIncrements(string $column = 'id'): Column
    {
        return $this->add('bigint', $column)->unsigned()->autoIncrement()->primary();
    }

    public function bigInteger(string $column): Column
    {
        return $this->add('bigint', $column);
    }

    public function integer(string $column): Column
    {
        return $this->add('int', $column);
    }

    public function unsignedInteger(string $column): Column
    {
        return $this->integer($column)->unsigned();
    }

    public function tinyInteger(string $column): Column
    {
        return $this->add('tinyint', $column);
    }

    public function smallInteger(string $column): Column
    {
        return $this->add('smallint', $column);
    }

    public function mediumInteger(string $column): Column
    {
        return $this->add('mediumint', $column);
    }

    public function string(string $column, int $length = 255): Column
    {
        return $this->add("varchar($length)", $column);
    }

    public function char(string $column, int $length = 255): Column
    {
        return $this->add("char($length)", $column);
    }

    public function text(string $column): Column
    {
        return $this->add('text', $column);
    }

    public function tinyText(string $column): Column
    {
        return $this->add('tinytext', $column);
    }

    public function mediumText(string $column): Column
    {
        return $this->add('mediumtext', $column);
    }

    public function longText(string $column): Column
    {
        return $this->add('longtext', $column);
    }

    public function float(string $column, ?int $precision = null, ?int $scale = null): Column
    {
        $type = $precision === null ? 'float' : "float($precision, $scale)";

        return $this->add($type, $column);
    }

    public function double(string $column, ?int $precision = null, ?int $scale = null): Column
    {
        $type = $precision === null ? 'double' : "double($precision, $scale)";

        return $this->add($type, $column);
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): Column
    {
        return $this->add("decimal($precision, $scale)", $column);
    }

    public function boolean(string $column): Column
    {
        return $this->add('tinyint(1)', $column);
    }

    public function date(string $column): Column
    {
        return $this->add('date', $column);
    }

    public function datetime(string $column, int $precision = 0): Column
    {
        $type = $precision > 0 ? "datetime($precision)" : 'datetime';

        return $this->add($type, $column);
    }

    public function timestamp(string $column, int $precision = 0): Column
    {
        $type = $precision > 0 ? "timestamp($precision)" : 'timestamp';

        return $this->add($type, $column);
    }

    public function time(string $column, int $precision = 0): Column
    {
        $type = $precision > 0 ? "time($precision)" : 'time';

        return $this->add($type, $column);
    }

    public function year(string $column): Column
    {
        return $this->add('year', $column);
    }

    public function json(string $column): Column
    {
        return $this->add('json', $column);
    }

    public function jsonb(string $column): Column
    {
        return $this->json($column);
    }

    public function binary(string $column): Column
    {
        return $this->add('blob', $column);
    }

    public function uuid(string $column): Column
    {
        return $this->add('char(36)', $column);
    }

    public function enum(string $column, array $values): Column
    {
        $values = implode(',', array_map(static fn (string $value) => "'".str_replace("'", "''", $value)."'", $values));

        return $this->add("enum($values)", $column);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(string $column = 'deleted_at'): Column
    {
        return $this->timestamp($column)->nullable();
    }

    public function columns(): array
    {
        return $this->columns;
    }

    protected function add(string $type, string $column): Column
    {
        $column = new Column($column, $type);
        $this->columns[] = $column;

        return $column;
    }

    public function toCreateSql(): string
    {
        $definitions = array_map(static fn (Column $column) => $column->toSql(), $this->columns);

        foreach ($this->columns as $column) {
            if ($column->unique) {
                $definitions[] = "UNIQUE KEY `{$column->name}_unique` (`{$column->name}`)";
            }
        }

        return 'CREATE TABLE `'.$this->name.'` ('
            ."\n  ".implode(",\n  ", $definitions)
            ."\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function toAlterSql(): string
    {
        $definitions = [];

        foreach ($this->columns as $column) {
            if ($column->unique) {
                $definitions[] = "ADD UNIQUE KEY `{$column->name}_unique` (`{$column->name}`)";
                continue;
            }

            $sql = 'ADD COLUMN '.$column->toSql();

            if ($column->after !== null) {
                $sql .= " AFTER `{$column->after}`";
            }

            $definitions[] = $sql;
        }

        return 'ALTER TABLE `'.$this->name.'` '.implode(', ', $definitions);
    }
}
