<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use Tests\Fixtures\Post;
use Tests\Fixtures\User;

final class RelationsTest extends TestCase
{
    public function test_has_many(): void
    {
        $user = $this->user();
        Post::create(['user_id' => $user->getKey(), 'title' => 'P1']);
        Post::create(['user_id' => $user->getKey(), 'title' => 'P2']);

        $posts = $user->posts()->get();

        $this->assertCount(2, $posts);
        $this->assertSame(2, $user->posts()->count());
    }

    public function test_belongs_to(): void
    {
        $user = $this->user();
        $post = Post::create(['user_id' => $user->getKey(), 'title' => 'P1']);

        $owner = $post->user()->get();

        $this->assertNotNull($owner);
        $this->assertSame($user->getKey(), $owner->getKey());
    }

    public function test_eager_loading_with(): void
    {
        $user = $this->user();
        Post::create(['user_id' => $user->getKey(), 'title' => 'P1']);

        $users = User::with('posts')->get();

        $this->assertCount(1, $users);
        $this->assertSame('P1', $users[0]->posts[0]->title);
    }

    private function user(): User
    {
        return User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret123']);
    }
}
