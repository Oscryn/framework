<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use Oscryn\Exceptions\ModelNotFoundException;
use RuntimeException;
use Tests\Fixtures\Todo;

final class ModelTest extends TestCase
{
    public function test_dirty_tracking_only_updates_changed_columns(): void
    {
        $todo = Todo::create(['title' => 'Original', 'completed' => false]);
        $createdAt = $todo->getAttribute('created_at');

        $this->assertFalse($todo->isDirty());

        $todo->update(['title' => 'Updated']);

        $this->assertSame($createdAt, $todo->getAttribute('created_at'));
        $this->assertSame('Updated', $todo->getAttribute('title'));
        $this->assertFalse($todo->isDirty());
    }

    public function test_is_dirty_detects_changes_before_save(): void
    {
        $todo = Todo::create(['title' => 'A']);

        $todo->title = 'B';

        $this->assertTrue($todo->isDirty('title'));
        $this->assertSame(['title'], array_keys($todo->getDirty()));
    }

    public function test_boolean_cast_round_trips(): void
    {
        $todo = Todo::create(['title' => 'Toggle', 'completed' => true]);

        $this->assertTrue($todo->completed);

        $todo->update(['completed' => false]);

        $this->assertFalse(Todo::find($todo->getKey())->completed);
    }

    public function test_fill_throws_on_non_fillable_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('$fillable');

        Todo::create(['title' => 'X', 'does_not_exist' => 'nope']);
    }

    public function test_find_or_fail_throws_model_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Todo::findOrFail(999);
    }

    public function test_first_or_create_returns_existing(): void
    {
        $first = Todo::create(['title' => 'Unique']);
        $second = Todo::firstOrCreate(['title' => 'Unique']);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertCount(1, Todo::all());
    }

    public function test_first_or_create_creates_when_missing(): void
    {
        $todo = Todo::firstOrCreate(['title' => 'New'], ['completed' => true]);

        $this->assertTrue($todo->completed);
        $this->assertCount(1, Todo::all());
    }

    public function test_update_or_create_updates_existing(): void
    {
        Todo::create(['title' => 'Existing', 'completed' => false]);

        $todo = Todo::updateOrCreate(['title' => 'Existing'], ['completed' => true]);

        $this->assertTrue($todo->completed);
        $this->assertCount(1, Todo::all());
    }

    public function test_update_or_create_creates_when_missing(): void
    {
        $todo = Todo::updateOrCreate(['title' => 'Missing'], ['completed' => true]);

        $this->assertTrue($todo->completed);
        $this->assertCount(1, Todo::all());
    }

    public function test_force_fill_bypasses_fillable_check(): void
    {
        $todo = Todo::create(['title' => 'Safe']);
        $todo->forceFill(['secret' => 'value']);

        $this->assertSame('value', $todo->getAttribute('secret'));
    }

    public function test_save_on_clean_model_preserves_created_at(): void
    {
        $todo = Todo::create(['title' => 'Static']);
        $createdAt = $todo->getAttribute('created_at');

        $this->assertTrue($todo->save());
        $this->assertSame($createdAt, $todo->getAttribute('created_at'));
    }
}
