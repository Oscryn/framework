<?php

declare(strict_types=1);

namespace Tests\Routing;

use Tests\TestCase;
use Oscryn\Exceptions\HttpException;
use Oscryn\Http\Csrf;
use Oscryn\Http\Request;
use Oscryn\Routing\Router;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Router::flush();
        Csrf::$except = [];
    }

    protected function tearDown(): void
    {
        Router::flush();
        Router::$controllerNamespace = 'App\Controllers\\';
        Csrf::$except = [];

        parent::tearDown();
    }

    public function test_unknown_route_throws_404_with_hint(): void
    {
        try {
            Router::dispatch($this->request('GET', '/nope'));
            $this->fail('Expected an HttpException');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status());
            $this->assertStringContainsString('GET /nope', $e->hint());
        }
    }

    public function test_post_without_csrf_throws_419(): void
    {
        Router::post('/secret', fn () => 'ok');

        try {
            Router::dispatch($this->request('POST', '/secret'));
            $this->fail('Expected an HttpException');
        } catch (HttpException $e) {
            $this->assertSame(419, $e->status());
            $this->assertStringContainsString('CSRF', $e->hint());
        }
    }

    public function test_post_with_csrf_token_passes(): void
    {
        Router::post('/secret', fn () => 'ok');

        $_POST['_token'] = Csrf::token();

        $this->assertSame('ok', Router::dispatch($this->request('POST', '/secret'))->content());
    }

    public function test_controller_string_action_resolution(): void
    {
        Router::$controllerNamespace = 'Tests\\Fixtures\\Controllers\\';
        Router::get('/hello', 'HelloController@index');

        $response = Router::dispatch($this->request('GET', '/hello'));

        $this->assertSame('hello from controller', $response->content());
    }

    public function test_controller_array_action_resolution(): void
    {
        Router::get('/hello', [\Tests\Fixtures\Controllers\HelloController::class, 'index']);

        $response = Router::dispatch($this->request('GET', '/hello'));

        $this->assertSame('hello from controller', $response->content());
    }

    public function test_route_group_prefix(): void
    {
        Router::group(['prefix' => 'admin'], static function (Router $router): void {
            $router->get('/dashboard', fn () => 'admin dashboard');
        });

        $response = Router::dispatch($this->request('GET', '/admin/dashboard'));

        $this->assertSame('admin dashboard', $response->content());
    }

    public function test_any_route_matches_get(): void
    {
        Router::any('/ping', fn () => 'pong');

        $this->assertSame('pong', Router::dispatch($this->request('GET', '/ping'))->content());
    }

    public function test_route_parameter_passed_to_closure(): void
    {
        Router::get('/users/{id}', fn ($id) => 'user-'.$id);

        $this->assertSame('user-42', Router::dispatch($this->request('GET', '/users/42'))->content());
    }

    protected function request(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        return new Request();
    }
}
