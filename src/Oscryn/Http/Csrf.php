<?php

namespace Oscryn\Http;

class Csrf
{
    protected const TOKEN_KEY = '_token';

    public static array $except = [];

    public static function except(string|array $uris): void
    {
        foreach ((array) $uris as $uri) {
            static::$except[] = $uri;
        }
    }

    public static function isExcept(string $path): bool
    {
        foreach (static::$except as $uri) {
            if ($uri === $path) {
                return true;
            }

            if (str_contains($uri, '*') && preg_match('#^'.str_replace('*', '.*', $uri).'$#', $path)) {
                return true;
            }
        }

        return false;
    }

    public static function token(): string
    {
        if (!Session::has(self::TOKEN_KEY)) {
            Session::put(self::TOKEN_KEY, bin2hex(random_bytes(32)));
        }

        return Session::get(self::TOKEN_KEY);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="'.static::token().'">';
    }

    public static function validate(Request $request): bool
    {
        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');

        return is_string($token) && $token !== '' && hash_equals(static::token(), $token);
    }
}
