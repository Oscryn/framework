<?php

namespace Oscryn\Routing;

use Closure;
use InvalidArgumentException;
use Oscryn\Http\Request;
use Oscryn\Http\Response;
use ReflectionFunction;
use ReflectionNamedType;

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
        $action = $this->resolveAction();

        return Response::from($action(...$this->actionArguments($action)));
    }

    protected function actionArguments(callable $action): array
    {
        $arguments = [];

        foreach ((new ReflectionFunction(Closure::fromCallable($action)))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && is_a($type->getName(), Request::class, true)) {
                $arguments[] = Request::capture();
                continue;
            }

            if ($this->parameters !== []) {
                $arguments[] = array_shift($this->parameters);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            $arguments[] = null;
        }

        return $arguments;
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
