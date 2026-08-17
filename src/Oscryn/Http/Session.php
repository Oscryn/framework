<?php

namespace Oscryn\Http;

class Session
{
    protected static bool $swept = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        static::sweep();
    }

    public static function flash(string $key, mixed $value): void
    {
        static::start();

        $_SESSION['_flash']['new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        static::start();

        return $_SESSION['_flash']['old'][$key] ?? $default;
    }

    public static function hasFlash(string $key): bool
    {
        static::start();

        return isset($_SESSION['_flash']['old'][$key]);
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = static::getFlash($key, $default);
        unset($_SESSION['_flash']['old'][$key]);

        return $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        static::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::start();

        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        static::start();

        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        static::start();

        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        static::start();

        session_regenerate_id(true);
    }

    protected static function sweep(): void
    {
        if (static::$swept) {
            return;
        }

        static::$swept = true;

        $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'] ?? [];
        unset($_SESSION['_flash']['new']);
    }
}
