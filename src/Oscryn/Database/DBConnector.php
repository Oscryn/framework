<?php

namespace Oscryn\Database;

use InvalidArgumentException;
use PDO;
use Throwable;

abstract class DBConnector
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $driver = strtolower(self::env('DB_DRIVER', self::env('DB_CONNECTION', 'mysql')));

            self::$connection = new PDO(
                self::dsn($driver),
                self::env('DB_USER', 'root'),
                self::env('DB_PASSWORD', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        return self::$connection;
    }

    public static function purge(): void
    {
        self::$connection = null;
    }

    public static function driver(): string
    {
        return strtolower(self::env('DB_DRIVER', self::env('DB_CONNECTION', 'mysql')));
    }

    protected static function dsn(string $driver): string
    {
        return match ($driver) {
            'sqlite', 'sqlite3' => 'sqlite:'.self::env('DB_NAME', ':memory:'),
            'pgsql' => 'pgsql:host='.self::env('DB_HOST', '127.0.0.1').';dbname='.self::env('DB_NAME', 'test'),
            default => 'mysql:host='.self::env('DB_HOST', '127.0.0.1').';dbname='.self::env('DB_NAME', 'test'),
        };
    }

    public static function beginTransaction(): void
    {
        static::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        static::connection()->commit();
    }

    public static function rollback(): void
    {
        static::connection()->rollBack();
    }

    public static function transaction(callable $callback): mixed
    {
        static::connection()->beginTransaction();

        try {
            $result = $callback();
            static::connection()->commit();

            return $result;
        } catch (Throwable $e) {
            static::connection()->rollBack();

            throw $e;
        }
    }

    public static function ensureDatabaseExists(): bool
    {
        $driver = self::driver();

        if ($driver === 'sqlite' || $driver === 'sqlite3') {
            $database = self::env('DB_NAME', ':memory:');

            if ($database !== ':memory:' && !is_file($database)) {
                $dir = dirname($database);

                if ($dir !== '' && !is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
            }

            return false;
        }

        $host = self::env('DB_HOST', '127.0.0.1');
        $user = self::env('DB_USER', 'root');
        $password = self::env('DB_PASSWORD', '');
        $database = self::env('DB_NAME', 'test');

        if (preg_match('/^[a-zA-Z0-9_]+$/', $database) !== 1) {
            throw new InvalidArgumentException('Invalid database name: '.$database);
        }

        $server = new PDO("mysql:host={$host}", $user, $password);

        $stmt = $server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
        $stmt->execute([$database]);

        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        return true;
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);

        return $value !== false && $value !== ''
            ? $value
            : (defined($key) ? (string) constant($key) : $default);
    }
}
