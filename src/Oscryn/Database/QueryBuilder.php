<?php

namespace Oscryn\Database;

use InvalidArgumentException;
use Oscryn\Extensions\Model;
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

    private bool $withTrashed = false;
    private array $with = [];

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

    public function withTrashed(): static
    {
        $this->withTrashed = true;

        return $this;
    }

    public function with(array|string ...$relations): static
    {
        foreach ($relations as $relation) {
            foreach ((array) $relation as $name) {
                $this->with[] = $name;
            }
        }

        return $this;
    }

    public function get(): array
    {
        $stmt = $this->pdo->prepare($this->toSql());
        $stmt->execute($this->bindings);

        $model = $this->model;
        $models = array_map(
            static fn (array $row): object => $model::fromRow($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );

        $this->eagerLoad($models);

        return $models;
    }

    public function first(): ?object
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    public function find(mixed $id): ?object
    {
        return $this->where('id', $id)->first();
    }

    public function create(array $attributes): Model
    {
        return $this->model::create($attributes);
    }

    public function insert(array $values): int
    {
        $columns = [];
        $placeholders = [];
        $bindings = [];

        foreach ($values as $key => $value) {
            $columns[] = '`'.$this->column($key).'`';
            $placeholders[] = '?';
            $bindings[] = $value;
        }

        if ($columns === []) {
            throw new InvalidArgumentException('Cannot insert an empty row.');
        }

        $sql = 'INSERT INTO '.$this->table.' ('.implode(', ', $columns).')'
            .' VALUES ('.implode(', ', $placeholders).')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $values): int
    {
        $sets = [];
        $bindings = [];

        foreach ($values as $key => $value) {
            $sets[] = '`'.$this->column($key).'` = ?';
            $bindings[] = $value;
        }

        if ($sets === []) {
            throw new InvalidArgumentException('Cannot update an empty row.');
        }

        $sql = 'UPDATE '.$this->table.' SET '.implode(', ', $sets).$this->whereSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($bindings, $this->bindings));

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM '.$this->table.$this->whereSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }

    public function count(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM '.$this->table.$this->whereSql());
        $stmt->execute($this->bindings);

        return (int) $stmt->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function paginate(int $perPage = 15, ?int $page = null): Paginator
    {
        $page = $page ?? max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->count();

        $items = (clone $this)
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    public function toSql(): string
    {
        $sql = 'SELECT * FROM '.$this->table.$this->whereSql();

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

    protected function whereSql(): string
    {
        $clauses = $this->wheres;

        if (!$this->withTrashed && $this->model::softDeletes()) {
            $clauses[] = '`deleted_at` IS NULL';
        }

        return $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses);
    }

    protected function eagerLoad(array $models): void
    {
        if ($models === [] || $this->with === []) {
            return;
        }

        foreach ($this->with as $name) {
            $segment = explode('.', $name)[0];
            $models[0]->$segment()->eagerLoad($models, $segment);
        }
    }

    private function column(string $column): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column) !== 1) {
            throw new InvalidArgumentException('Invalid column name: '.$column);
        }

        return $column;
    }
}
