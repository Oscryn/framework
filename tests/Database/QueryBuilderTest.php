<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use Oscryn\Database\DBConnector;
use Tests\Fixtures\Todo;

final class QueryBuilderTest extends TestCase
{
    public function test_insert_binds_boolean_as_integer(): void
    {
        Todo::create(['title' => 'Pay bills', 'completed' => false]);

        $row = $this->rawTodos()[0];

        $this->assertSame(0, (int) $row['completed']);
        $this->assertSame(false, Todo::find(1)->getAttribute('completed'));
    }

    public function test_where_and_or_where(): void
    {
        Todo::create(['title' => 'Alpha', 'completed' => true]);
        Todo::create(['title' => 'Beta', 'completed' => false]);
        Todo::create(['title' => 'Gamma', 'completed' => true]);

        $completed = Todo::query()->where('completed', true)->get();
        $this->assertCount(2, $completed);

        $titles = array_map(static fn ($t) => $t->title, $completed);
        $this->assertSame(['Alpha', 'Gamma'], $titles);

        $or = Todo::query()->where('title', 'Alpha')->orWhere('title', 'Beta')->get();
        $this->assertCount(2, $or);
    }

    public function test_where_in_and_where_not_in(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);
        Todo::create(['title' => 'C']);

        $in = Todo::query()->whereIn('title', ['A', 'C'])->get();
        $this->assertCount(2, $in);

        $notIn = Todo::query()->whereNotIn('title', ['A'])->get();
        $this->assertCount(2, $notIn);
    }

    public function test_where_null_and_where_not_null(): void
    {
        $todo = Todo::create(['title' => 'Delete me']);
        Todo::query()->where('id', $todo->getKey())->update(['deleted_at' => null]);

        $notNull = Todo::query()->whereNotNull('id')->get();
        $this->assertCount(1, $notNull);

        $null = Todo::query()->whereNull('deleted_at')->get();
        $this->assertCount(1, $null);
    }

    public function test_when_conditionally_applies_constraints(): void
    {
        Todo::create(['title' => 'A', 'completed' => false]);
        Todo::create(['title' => 'B', 'completed' => true]);

        $result = Todo::query()
            ->when(true, fn ($q) => $q->where('completed', true))
            ->get();

        $this->assertCount(1, $result);
        $this->assertSame('B', $result[0]->title);
    }

    public function test_order_limit_offset(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);
        Todo::create(['title' => 'C']);

        $page = Todo::query()->orderBy('title', 'desc')->limit(2)->offset(1)->get();

        $this->assertCount(2, $page);
        $this->assertSame('B', $page[0]->title);
        $this->assertSame('A', $page[1]->title);
    }

    public function test_to_sql_renders_group_by_and_join(): void
    {
        $sql = Todo::query()
            ->groupBy('completed')
            ->join('users', 'todos.id', '=', 'users.id')
            ->toSql();

        $this->assertStringContainsString('INNER JOIN users ON todos.id = users.id', $sql);
        $this->assertStringContainsString('GROUP BY completed', $sql);
    }

    private function rawTodos(): array
    {
        return DBConnector::connection()->query('SELECT * FROM todos')->fetchAll();
    }
}
