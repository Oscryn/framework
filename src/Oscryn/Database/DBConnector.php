<?php

namespace Oscryn\Database;

use InvalidArgumentException;
use PDO;

abstract class DBConnector
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = new PDO(
                'mysql:host='.self::env('DB_HOST', '127.0.0.1').';dbname='.self::env('DB_NAME', 'test'),
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

    public static function ensureDatabaseExists(): bool
    {
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
