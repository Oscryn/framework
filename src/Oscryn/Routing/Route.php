<?php

namespace Oscryn\Routing;

use Closure;
use InvalidArgumentException;
use Oscryn\Http\Response;

class Route
{
    protected array $methods;
    protected string $uri;
    protected mixed $action;
    protected array $parameters = [];
    protected ?string $compiledRegex = null;

    public function __construct(array $methods, string $uri, mixed $action)
    {
        $this->methods = array_map('strtoupper', $methods);
        $this->uri = $uri;
        $this->action = $action;
    }

    public function matches(string $method, string $path): bool
    {
        if (!in_array(strtoupper($method), $this->methods, true)) {
            return false;
        }

        if (preg_match($this->regex(), $path, $matches) !== 1) {
            return false;
        }

        $this->parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        return true;
    }

    public function run(): Response
    {
        return Response::from($this->resolveAction()(...array_values($this->parameters)));
    }

    protected function regex(): string
    {
        if ($this->compiledRegex === null) {
            $uri = preg_replace_callback('/\{(\w+)\?\}/', static fn (array $m) => '(?:/(?P<'.$m[1].'>[^/]+))?', $this->uri);
            $uri = preg_replace_callback('/\{(\w+)\}/', static fn (array $m) => '(?P<'.$m[1].'>[^/]+)', $uri);

            $this->compiledRegex = '#^/'.ltrim($uri, '/').'/?$#';
        }

        return $this->compiledRegex;
    }

    protected function resolveAction(): callable
    {
        if ($this->action instanceof Closure) {
            return $this->action;
        }

        if (is_string($this->action)) {
            $parts = preg_split('/@|::/', $this->action);
            $class = $parts[0];
            $method = $parts[1] ?? '__invoke';

            if (!str_contains($class, '\\')) {
                $class = Router::$controllerNamespace.$class;
            }

            return [new $class(), $method];
        }

        if (is_array($this->action)) {
            [$class, $method] = $this->action;

            return [is_object($class) ? $class : new $class(), $method];
        }

        throw new InvalidArgumentException('Invalid route action for URI "'.$this->uri.'".');
    }
}
