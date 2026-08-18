<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use InvalidArgumentException;
use Oscryn\Database\DBConnector;
use Oscryn\Exceptions\ModelNotFoundException;
use Tests\Fixtures\Todo;

final class QueryBuilderAdvancedTest extends TestCase
{
    public function test_or_where_in(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);
        Todo::create(['title' => 'C']);

        $result = Todo::query()->where('title', 'A')->orWhereIn('title', ['B', 'C'])->get();

        $this->assertCount(3, $result);
    }

    public function test_or_where_null_and_where_not_null(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);

        DBConnector::connection()->exec("UPDATE todos SET deleted_at = '2020-01-01 00:00:00' WHERE title = 'B'");

        $null = Todo::query()->where('title', 'A')->orWhereNull('deleted_at')->get();
        $this->assertCount(1, $null);

        $notNull = Todo::query()->whereNotNull('deleted_at')->get();
        $this->assertCount(1, $notNull);
        $this->assertSame('B', $notNull[0]->title);
    }

    public function test_where_in_empty_matches_nothing_and_not_in_empty_matches_all(): void
    {
        Todo::create(['title' => 'A']);

        $this->assertCount(0, Todo::query()->whereIn('title', [])->get());
        $this->assertCount(1, Todo::query()->whereNotIn('title', [])->get());
    }

    public function test_where_with_explicit_operator(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);
        Todo::create(['title' => 'C']);

        $result = Todo::query()->where('id', '>', 1)->get();

        $this->assertCount(2, $result);
    }

    public function test_first_find_and_find_or_fail(): void
    {
        Todo::create(['title' => 'First']);

        $this->assertSame('First', Todo::query()->first()->title);
        $this->assertSame('First', Todo::query()->find(1)->title);
        $this->assertNull(Todo::query()->find(999));
        $this->assertSame('First', Todo::query()->findOrFail(1)->title);
    }

    public function test_first_or_fail_throws_when_empty(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Todo::query()->firstOrFail();
    }

    public function test_find_or_fail_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Todo::query()->findOrFail(999);
    }

    public function test_count_and_exists(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);

        $this->assertSame(2, Todo::count());
        $this->assertTrue(Todo::query()->where('title', 'A')->exists());
        $this->assertFalse(Todo::query()->where('title', 'Z')->exists());
    }

    public function test_update_returns_affected_row_count(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);

        $rows = Todo::query()->where('completed', false)->update(['completed' => true]);

        $this->assertSame(2, $rows);
        $this->assertSame(2, Todo::query()->where('completed', true)->count());
    }

    public function test_delete_returns_affected_row_count(): void
    {
        Todo::create(['title' => 'A']);
        Todo::create(['title' => 'B']);

        $rows = Todo::query()->where('title', 'A')->delete();

        $this->assertSame(1, $rows);
        $this->assertCount(1, Todo::all());
    }

    public function test_insert_empty_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Todo::query()->insert([]);
    }

    public function test_update_empty_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Todo::query()->where('id', 1)->update([]);
    }

    public function test_invalid_operator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Todo::query()->where('title', 'BETWEEN', 'x');
    }

    public function test_invalid_column_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Todo::query()->where('title; DROP TABLE todos', 'x');
    }

    public function test_when_applies_default_callback(): void
    {
        Todo::create(['title' => 'A', 'completed' => false]);
        Todo::create(['title' => 'B', 'completed' => true]);

        $result = Todo::query()
            ->when(false, fn ($q) => $q->where('completed', true), fn ($q) => $q->where('completed', false))
            ->get();

        $this->assertCount(1, $result);
        $this->assertSame('A', $result[0]->title);
    }

    public function test_left_join_renders_in_sql(): void
    {
        $sql = Todo::query()->leftJoin('users', 'todos.id', '=', 'users.id')->toSql();

        $this->assertStringContainsString('LEFT JOIN users ON todos.id = users.id', $sql);
    }

    public function test_paginate(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Todo::create(['title' => "T{$i}"]);
        }

        $page = Todo::query()->paginate(2, 2);

        $this->assertCount(2, $page->items());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(5, $page->total());
        $this->assertSame(3, $page->lastPage());
    }

    public function test_create_via_query_builder(): void
    {
        $todo = Todo::query()->create(['title' => 'Made via query']);

        $this->assertSame('Made via query', $todo->title);
        $this->assertTrue($todo->exists());
    }
}
