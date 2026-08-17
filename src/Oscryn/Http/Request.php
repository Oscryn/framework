<?php

namespace Oscryn\Http;

class Request
{
    protected array $query;
    protected array $body;
    protected array $server;
    protected array $headers;
    protected ?array $jsonBody = null;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->server = $_SERVER;
        $this->headers = $this->extractHeaders();
    }

    public static function capture(): static
    {
        return new static();
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $path = parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return $path ?: '/';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $this->json()[$key] ?? null;

        return $value ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body, $this->json() ?? []);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);

        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === $key) {
                return $value;
            }
        }

        return $default;
    }

    public function json(): ?array
    {
        if ($this->jsonBody === null && str_contains($this->header('Content-Type', ''), 'application/json')) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            $this->jsonBody = is_array($decoded) ? $decoded : null;
        }

        return $this->jsonBody;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    protected function extractHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
