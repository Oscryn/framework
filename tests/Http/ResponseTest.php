<?php

declare(strict_types=1);

namespace Tests\Http;

use Oscryn\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('APP_ENV=testing');
    }

    public function test_from_string(): void
    {
        $response = Response::from('hello');

        $this->assertSame('hello', $response->content());
        $this->assertSame(200, $response->status());
    }

    public function test_from_array_returns_json(): void
    {
        $response = Response::from(['foo' => 'bar']);

        $this->assertStringContainsString('"foo"', $response->content());
        $this->assertStringContainsString('application/json', $response->headers()['Content-Type']);
    }

    public function test_from_response_returns_same_instance(): void
    {
        $original = new Response('original');

        $this->assertSame($original, Response::from($original));
    }

    public function test_json_response_content_type(): void
    {
        $response = Response::json(['x' => 1]);

        $this->assertStringContainsString('application/json', $response->headers()['Content-Type']);
        $this->assertStringContainsString('"x"', $response->content());
    }

    public function test_redirect_sets_location_and_status(): void
    {
        $response = (new Response())->redirect('/login', 303);

        $this->assertSame(303, $response->status());
        $this->assertSame('/login', $response->headers()['Location']);
    }

    public function test_fluent_setters(): void
    {
        $response = (new Response())
            ->setContent('body')
            ->setStatus(201)
            ->header('X-Custom', 'yes');

        $this->assertSame('body', $response->content());
        $this->assertSame(201, $response->status());
        $this->assertSame('yes', $response->headers()['X-Custom']);
    }
}
