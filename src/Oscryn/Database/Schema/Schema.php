<?php

namespace Oscryn\Database\Schema;

use PDO;
use Oscryn\Database\DBConnector;

class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Table($table);
        $callback($blueprint);
        static::connection()->exec($blueprint->toCreateSql());
    }

    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Table($table);
        $callback($blueprint);
        static::connection()->exec($blueprint->toAlterSql());
    }

    public static function drop(string $table): void
    {
        static::connection()->exec("DROP TABLE `{$table}`");
    }

    public static function dropIfExists(string $table): void
    {
        static::connection()->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    public static function hasTable(string $table): bool
    {
        $stmt = static::connection()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            .'WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }

    public static function connection(): PDO
    {
        return DBConnector::connection();
    }
}
