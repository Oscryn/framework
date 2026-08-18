<?php

namespace Oscryn\Database;

use InvalidArgumentException;
use Oscryn\Exceptions\ModelNotFoundException;
use Oscryn\Extensions\Model;
use PDO;
use PDOStatement;

class QueryBuilder
{
    private PDO $pdo;
    private string $table;
    private string $model;

    private array $wheres = [];
    private array $bindings = [];
    private array $orders = [];
    private array $groups = [];
    private array $joins = [];
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

        return $this->addWhere('AND', $column, $operator, $value);
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->addWhere('OR', $column, $operator, $value);
    }

    public function whereIn(string $column, array $values): static
    {
        return $this->addWhereIn('AND', false, $column, $values);
    }

    public function orWhereIn(string $column, array $values): static
    {
        return $this->addWhereIn('OR', false, $column, $values);
    }

    public function whereNotIn(string $column, array $values): static
    {
        return $this->addWhereIn('AND', true, $column, $values);
    }

    public function whereNull(string $column): static
    {
        return $this->addWhereNull('AND', false, $column);
    }

    public function orWhereNull(string $column): static
    {
        return $this->addWhereNull('OR', false, $column);
    }

    public function whereNotNull(string $column): static
    {
        return $this->addWhereNull('AND', true, $column);
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->addWhereNull('OR', true, $column);
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = $this->column($column).' '.$direction;

        return $this;
    }

    public function groupBy(string|array ...$columns): static
    {
        foreach ($columns as $column) {
            foreach ((array) $column as $name) {
                $this->groups[] = $this->column($name);
            }
        }

        return $this;
    }

    public function join(string $table, string $first, mixed $operator = null, mixed $second = null): static
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    public function leftJoin(string $table, string $first, mixed $operator = null, mixed $second = null): static
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    public function rightJoin(string $table, string $first, mixed $operator = null, mixed $second = null): static
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
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

    public function when(mixed $condition, callable $callback, ?callable $default = null): static
    {
        if ($condition) {
            $callback($this);
        } elseif ($default !== null) {
            $default($this);
        }

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
        $this->execute($stmt, $this->bindings);

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

    public function firstOrFail(): Model
    {
        $model = $this->first();

        if ($model === null) {
            throw new ModelNotFoundException($this->model);
        }

        return $model;
    }

    public function find(mixed $id): ?object
    {
        return $this->where('id', $id)->first();
    }

    public function findOrFail(mixed $id): Model
    {
        $model = $this->find($id);

        if ($model === null) {
            throw new ModelNotFoundException($this->model, [$id]);
        }

        return $model;
    }

    public function create(array $attributes): Model
    {
        return $this->model::create($attributes);
    }

    public function firstWhere(array $wheres): ?object
    {
        foreach ($wheres as $column => $value) {
            $this->where($column, $value);
        }

        return $this->first();
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
        $this->execute($stmt, $bindings);

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
        $this->execute($stmt, array_merge($bindings, $this->bindings));

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM '.$this->table.$this->whereSql();
        $stmt = $this->pdo->prepare($sql);
        $this->execute($stmt, $this->bindings);

        return $stmt->rowCount();
    }

    public function count(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM '.$this->table.$this->whereSql());
        $this->execute($stmt, $this->bindings);

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
        $sql = 'SELECT * FROM '.$this->table;

        foreach ($this->joins as $join) {
            $sql .= ' '.$join;
        }

        $sql .= $this->whereSql();

        if ($this->groups !== []) {
            $sql .= ' GROUP BY '.implode(', ', $this->groups);
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

    protected function whereSql(): string
    {
        $clauses = [];

        foreach ($this->wheres as $where) {
            $clauses[] = ['sql' => $where['sql'], 'boolean' => $where['boolean']];
        }

        if (!$this->withTrashed && $this->model::softDeletes()) {
            $clauses[] = ['sql' => '`deleted_at` IS NULL', 'boolean' => 'AND'];
        }

        if ($clauses === []) {
            return '';
        }

        $sql = ' WHERE '.$clauses[0]['sql'];

        for ($i = 1; $i < count($clauses); $i++) {
            $sql .= ' '.$clauses[$i]['boolean'].' '.$clauses[$i]['sql'];
        }

        return $sql;
    }

    protected function addWhere(string $boolean, string $column, mixed $operator, mixed $value): static
    {
        $this->wheres[] = [
            'sql' => $this->column($column).' '.$this->operator($operator).' ?',
            'boolean' => $boolean,
        ];
        $this->bindings[] = $value;

        return $this;
    }

    protected function addWhereIn(string $boolean, bool $not, string $column, array $values): static
    {
        if ($values === []) {
            $this->wheres[] = [
                'sql' => $not ? '1 = 1' : '1 = 0',
                'boolean' => $boolean,
            ];

            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $keyword = $not ? 'NOT IN' : 'IN';

        $this->wheres[] = [
            'sql' => $this->column($column).' '.$keyword.' ('.$placeholders.')',
            'boolean' => $boolean,
        ];
        $this->bindings = array_merge($this->bindings, array_values($values));

        return $this;
    }

    protected function addWhereNull(string $boolean, bool $not, string $column): static
    {
        $this->wheres[] = [
            'sql' => $this->column($column).' IS '.($not ? 'NOT NULL' : 'NULL'),
            'boolean' => $boolean,
        ];

        return $this;
    }

    protected function addJoin(string $type, string $table, string $first, mixed $operator, mixed $second): static
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $this->joins[] = $type.' JOIN '.$this->identifier($table)
            .' ON '.$this->column($first).' '.$this->operator($operator).' '.$this->column($second);

        return $this;
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

    protected function execute(PDOStatement $stmt, array $bindings): void
    {
        foreach ($bindings as $index => $value) {
            if (is_bool($value)) {
                $value = (int) $value;
            }

            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_int($value)  => PDO::PARAM_INT,
                default         => PDO::PARAM_STR,
            };

            $stmt->bindValue($index + 1, $value, $type);
        }

        $stmt->execute();
    }

    protected function column(string $column): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column) !== 1) {
            throw new InvalidArgumentException('Invalid column name: '.$column);
        }

        return $column;
    }

    protected function identifier(string $identifier): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException('Invalid identifier: '.$identifier);
        }

        return $identifier;
    }

    protected function operator(mixed $operator): string
    {
        $operator = strtoupper((string) $operator);

        if (!in_array($operator, ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'], true)) {
            throw new InvalidArgumentException('Invalid operator: '.$operator);
        }

        return $operator;
    }
}
