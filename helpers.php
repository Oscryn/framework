<?php

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (getenv($key) !== false) {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value !== false && $value !== ''
            ? $value
            : (defined($key) ? (string) constant($key) : $default);
    }
}

if (!function_exists('app_env')) {
    function app_env(?string $environment = null): string|bool
    {
        $value = env('APP_ENV', 'local');

        return $environment === null ? $value : $value === $environment;
    }
}

if (!function_exists('encrypt')) {
    function encrypt(mixed $value): string
    {
        return \Oscryn\Encryption\Encrypter::get()->encrypt($value);
    }
}

if (!function_exists('decrypt')) {
    function decrypt(string $payload): mixed
    {
        return \Oscryn\Encryption\Encrypter::get()->decrypt($payload);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \Oscryn\Http\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return \Oscryn\Http\Csrf::field();
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $value): void
    {
        \Oscryn\Http\Session::flash($key, $value);
    }
}

if (!function_exists('get_flash')) {
    function get_flash(string $key, mixed $default = null): mixed
    {
        return \Oscryn\Http\Session::getFlash($key, $default);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $location, int $status = 302): \Oscryn\Http\Response
    {
        return (new \Oscryn\Http\Response)->redirect($location, $status);
    }
}

if (!function_exists('db')) {
    function db(): \PDO
    {
        return \Oscryn\Database\DBConnector::connection();
    }
}

if (!function_exists('request')) {
    function request(): \Oscryn\Http\Request
    {
        return \Oscryn\Http\Request::capture();
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = [], int $status = 200): \Oscryn\Http\Response
    {
        return \Oscryn\View\View::make($template, $data, $status);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): void
    {
        \Oscryn\Support\Dumper::dump('dd', ...$values);
        exit(1);
    }
}

if (!function_exists('live_dump')) {
    function live_dump(mixed ...$values): mixed
    {
        return \Oscryn\Support\Dumper::dump('live_dump', ...$values);
    }
}
