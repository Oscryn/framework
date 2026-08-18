<?php

declare(strict_types=1);

namespace Tests\Database;

use Oscryn\Database\Schema\Table;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function test_create_sql_with_foreign_key(): void
    {
        $table = new Table('posts');
        $table->id();
        $table->string('title');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        $sql = $table->toCreateSql();

        $this->assertStringContainsString(
            'CONSTRAINT `posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
            $sql
        );
        $this->assertStringContainsString('ON DELETE CASCADE', $sql);
    }

    public function test_foreign_key_constrained_guesses_table(): void
    {
        $table = new Table('posts');
        $table->foreign('user_id')->constrained();

        $sql = $table->toCreateSql();

        $this->assertStringContainsString('REFERENCES `users` (`id`)', $sql);
    }

    public function test_index_and_unique_definitions(): void
    {
        $table = new Table('posts');
        $table->index(['user_id', 'title'], 'idx_user_title');
        $table->unique(['email']);

        $sql = $table->toCreateSql();

        $this->assertStringContainsString('KEY `idx_user_title` (`user_id`, `title`)', $sql);
        $this->assertStringContainsString('UNIQUE KEY `posts_email_unique` (`email`)', $sql);
    }

    public function test_column_default_quoting(): void
    {
        $table = new Table('widgets');
        $table->boolean('active')->default(true);
        $table->string('status')->default('new');
        $table->integer('count')->default(0);
        $table->string('note')->default(null);

        $sql = $table->toCreateSql();

        $this->assertStringContainsString('DEFAULT 1', $sql);
        $this->assertStringContainsString("DEFAULT 'new'", $sql);
        $this->assertStringContainsString('DEFAULT 0', $sql);
        $this->assertStringContainsString('DEFAULT NULL', $sql);
    }

    public function test_alter_sql_adds_foreign_and_index(): void
    {
        $table = new Table('posts');
        $table->foreign('user_id')->constrained();
        $table->index(['title']);

        $sql = $table->toAlterSql();

        $this->assertStringContainsString('ADD CONSTRAINT', $sql);
        $this->assertStringContainsString('ADD KEY `posts_title_index` (`title`)', $sql);
    }
}
