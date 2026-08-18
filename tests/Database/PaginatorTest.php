<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use Tests\Fixtures\Todo;

final class PaginatorTest extends TestCase
{
    public function test_paginate_basic(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Todo::create(['title' => "T{$i}"]);
        }

        $page = Todo::paginate(2);

        $this->assertCount(2, $page->items());
        $this->assertSame(5, $page->total());
        $this->assertSame(2, $page->perPage());
        $this->assertSame(1, $page->currentPage());
        $this->assertSame(3, $page->lastPage());
        $this->assertTrue($page->hasPages());
        $this->assertTrue($page->hasMorePages());
        $this->assertSame(2, $page->nextPage());
        $this->assertNull($page->previousPage());
    }

    public function test_previous_and_next_page(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Todo::create(['title' => "T{$i}"]);
        }

        $page = Todo::paginate(2, 2);

        $this->assertSame(1, $page->previousPage());
        $this->assertSame(3, $page->nextPage());
    }

    public function test_links_renders_pagination_html(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Todo::create(['title' => "T{$i}"]);
        }

        $links = Todo::paginate(2)->links();

        $this->assertStringContainsString('Previous', $links);
        $this->assertStringContainsString('Next', $links);
        $this->assertStringContainsString('page=2', $links);
    }

    public function test_to_array_shape(): void
    {
        Todo::create(['title' => 'A']);

        $array = Todo::paginate(1)->toArray();

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('per_page', $array);
        $this->assertArrayHasKey('current_page', $array);
        $this->assertArrayHasKey('last_page', $array);
    }
}
