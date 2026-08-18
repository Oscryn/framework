<?php

declare(strict_types=1);

namespace Tests\Routing;

use Tests\TestCase;
use Closure;
use Oscryn\Exceptions\ModelNotFoundException;
use Oscryn\Http\Request;
use Oscryn\Routing\Route;
use Oscryn\Routing\Router;
use Tests\Fixtures\Todo;

final class RouteTest extends TestCase
{
    protected function tearDown(): void
    {
        Router::flush();

        parent::tearDown();
    }

    public function test_model_binding_resolves_by_route_param_name(): void
    {
        Todo::create(['title' => 'Buy milk']);

        $route = new Route(['GET'], '/todos/{todo}', fn (Todo $todo) => $todo->title);

        $this->assertTrue($route->matches('GET', '/todos/1'));
        $this->assertSame('Buy milk', $route->run()->content());
    }

    public function test_model_binding_falls_back_to_id_param(): void
    {
        Todo::create(['title' => 'Buy bread']);

        $route = new Route(['GET'], '/todos/{id}', fn (Todo $todo) => $todo->title);

        $this->assertTrue($route->matches('GET', '/todos/1'));
        $this->assertSame('Buy bread', $route->run()->content());
    }

    public function test_model_binding_throws_when_missing(): void
    {
        $route = new Route(['GET'], '/todos/{todo}', fn (Todo $todo) => $todo->title);

        $this->assertTrue($route->matches('GET', '/todos/999'));

        $this->expectException(ModelNotFoundException::class);
        $route->run();
    }

    public function test_middleware_pipeline_runs_around_the_action(): void
    {
        Router::flush();

        Router::get('/hello', fn () => 'world')->middleware(
            static function (Request $request, Closure $next) {
                $response = $next($request);

                return $response->setContent(strtoupper($response->content()));
            }
        );

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/hello';

        $response = Router::dispatch(new Request());

        $this->assertSame('WORLD', $response->content());
    }
}
