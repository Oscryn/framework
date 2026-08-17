<?php

namespace Oscryn\Database;

use InvalidArgumentException;
use PDO;

class QueryBuilder
{
    private PDO $pdo;
    private string $table;
    private string $model;

    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $model, string $table)
    {
        $this->pdo = $model::connection();
        $this->table = $table;
        $this->model = $model;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = $this->column($column).' '.$operator.' ?';
        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->wheres[] = $this->column($column).' IN ('.$placeholders.')';
        $this->bindings = array_merge($this->bindings, array_values($values));

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = $this->column($column).' '.$direction;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function get(): array
    {
        $stmt = $this->pdo->prepare($this->toSql());
        $stmt->execute($this->bindings);

        $model = $this->model;

        return array_map(
            static fn (array $row): object => $model::fromRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function first(): ?object
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    public function find(mixed $id): ?object
    {
        return $this->where('id', $id)->first();
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM '.$this->table;

        if ($this->wheres !== []) {
            $sql .= ' WHERE '.implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return (int) $stmt->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function toSql(): string
    {
        $sql = 'SELECT * FROM '.$this->table;

        if ($this->wheres !== []) {
            $sql .= ' WHERE '.implode(' AND ', $this->wheres);
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY '.implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT '.$this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET '.$this->offset;
        }

        return $sql;
    }

    private function column(string $column): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column) !== 1) {
            throw new InvalidArgumentException('Invalid column name: '.$column);
        }

        return $column;
    }
}
