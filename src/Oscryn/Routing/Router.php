<?php

namespace Oscryn\Routing;

use Closure;
use Oscryn\Exceptions\HttpException;
use Oscryn\Http\Csrf;
use Oscryn\Http\Request;
use Oscryn\Http\Response;

class Router
{
    public static string $controllerNamespace = 'App\Controllers\\';

    protected static ?self $instance = null;

    protected array $routes = [];

    protected array $middleware = [];

    protected array $groupStack = [];

    public static function instance(): self
    {
        return static::$instance ??= new static();
    }

    public static function flush(): void
    {
        static::$instance = new static();
    }

    public static function get(string $uri, mixed $action): Route
    {
        return static::instance()->add(['GET', 'HEAD'], $uri, $action);
    }

    public static function post(string $uri, mixed $action): Route
    {
        return static::instance()->add(['POST'], $uri, $action);
    }

    public static function put(string $uri, mixed $action): Route
    {
        return static::instance()->add(['PUT'], $uri, $action);
    }

    public static function patch(string $uri, mixed $action): Route
    {
        return static::instance()->add(['PATCH'], $uri, $action);
    }

    public static function delete(string $uri, mixed $action): Route
    {
        return static::instance()->add(['DELETE'], $uri, $action);
    }

    public static function any(string $uri, mixed $action): Route
    {
        return static::instance()->add(
            ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            $uri,
            $action
        );
    }

    public static function middleware(array|string $middleware): void
    {
        foreach ((array) $middleware as $name) {
            static::instance()->middleware[] = $name;
        }
    }

    public static function group(array $attributes, callable $callback): void
    {
        static::instance()->addGroup($attributes, $callback);
    }

    public static function dispatch(Request $request): Response
    {
        return static::instance()->handle($request);
    }

    public function add(array $methods, string $uri, mixed $action): Route
    {
        $route = new Route($methods, $this->prefixUri($uri), $action);
        $this->routes[] = $route;

        return $route;
    }

    public function addGroup(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function handle(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
            && !Csrf::isExcept($path)
            && !Csrf::validate($request)) {
            throw new HttpException(
                419,
                'Page Expired',
                'Your CSRF token is missing or invalid. Include a hidden {csrf_field()} (or @csrf) in your form and avoid resubmitting expired sessions.'
            );
        }

        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $this->runMiddleware(
                    array_merge($this->middleware, $route->getMiddleware()),
                    $request,
                    static fn (Request $request): Response => $route->run()
                );
            }
        }

        throw new HttpException(404, 'Not Found', 'No route matched '.$method.' '.$path.'.');
    }

    protected function runMiddleware(array $middleware, Request $request, Closure $core): Response
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            static function (callable $next, mixed $middleware): Closure {
                return static function (Request $request) use ($next, $middleware): Response {
                    if ($middleware instanceof Closure) {
                        return $middleware($request, $next);
                    }

                    $instance = is_string($middleware) ? new $middleware() : $middleware;

                    return $instance->handle($request, $next);
                };
            },
            $core
        );

        return $pipeline($request);
    }

    protected function prefixUri(string $uri): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= '/'.trim($group['prefix'] ?? '', '/');
        }

        return $prefix === '' ? $uri : $prefix.'/'.ltrim($uri, '/');
    }
}
