<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use Tests\Fixtures\Note;

final class SoftDeletesTest extends TestCase
{
    public function test_query_excludes_trashed_by_default(): void
    {
        Note::create(['body' => 'A']);
        Note::create(['body' => 'B'])->delete();

        $this->assertCount(1, Note::all());
        $this->assertSame('A', Note::all()[0]->body);
    }

    public function test_with_trashed_includes_soft_deleted(): void
    {
        Note::create(['body' => 'A']);
        Note::create(['body' => 'B'])->delete();

        $this->assertSame(2, Note::withTrashed()->count());
    }

    public function test_delete_soft_deletes_and_marks_trashed(): void
    {
        $note = Note::create(['body' => 'A']);

        $note->delete();

        $this->assertTrue($note->trashed());
        $this->assertNotNull($note->getAttribute('deleted_at'));
        $this->assertTrue($note->exists());
    }

    public function test_restore(): void
    {
        $note = Note::create(['body' => 'A']);
        $note->delete();

        $this->assertCount(0, Note::all());

        $this->assertTrue($note->restore());

        $this->assertFalse($note->trashed());
        $this->assertCount(1, Note::all());
    }

    public function test_force_delete(): void
    {
        $note = Note::create(['body' => 'A']);
        $note->delete();

        $this->assertTrue($note->forceDelete());
        $this->assertSame(0, Note::withTrashed()->count());
        $this->assertFalse($note->exists());
    }
}
