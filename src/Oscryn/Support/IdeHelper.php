<?php

namespace Oscryn\Support;

use Oscryn\Database\DBConnector;
use Oscryn\Extensions\Model;
use PDO;

class IdeHelper
{
    public static function generate(string $modelsDir): array
    {
        $files = glob(rtrim($modelsDir, '/').'/*.php') ?: [];
        $results = [];

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);

            if (!preg_match('/(?:abstract\s+|final\s+)?class\s+(\w+)/', $content, $match)) {
                continue;
            }

            if (str_contains($content, 'abstract class '.$match[1])) {
                continue;
            }

            $short = $match[1];
            $fqcn = static::fqcn($content, $short);

            if (!class_exists($fqcn) && is_file($file)) {
                require_once $file;
            }

            if (!class_exists($fqcn)) {
                $results[] = "SKIP {$file}: class {$fqcn} not found";

                continue;
            }

            if (!is_subclass_of($fqcn, Model::class)) {
                continue;
            }

            try {
                $columns = static::columns($fqcn::table());
            } catch (\Throwable $e) {
                $results[] = "SKIP {$file}: no table ({$fqcn::table()})";

                continue;
            }

            $docblock = static::docblock(new $fqcn(), $columns);
            $updated = static::inject($content, $short, $docblock);

            if ($updated !== $content) {
                file_put_contents($file, $updated);
                $results[] = "OK {$file}: ".count($columns).' properties';
            }
        }

        return $results;
    }

    protected static function fqcn(string $content, string $short): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $match)) {
            return trim($match[1], ' \\').'\\'.$short;
        }

        return $short;
    }

    protected static function columns(string $table): array
    {
        $table = str_replace('`', '', $table);

        $stmt = DBConnector::connection()->prepare("SHOW COLUMNS FROM `{$table}`");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected static function docblock(Model $model, array $columns): string
    {
        $casts = $model->getCasts();
        $lines = ['/**'];

        foreach ($columns as $column) {
            $name = $column['Field'];
            $nullable = ($column['Null'] ?? 'NO') === 'YES';
            $type = isset($casts[$name])
                ? static::castType($casts[$name], $nullable)
                : static::sqlType($column['Type'], $nullable);

            $lines[] = ' * @property '.$type.' $'.$name;
        }

        $lines[] = ' */';

        return implode("\n", $lines);
    }

    protected static function castType(string $cast, bool $nullable): string
    {
        $type = match ($cast) {
            'int', 'integer'       => 'int',
            'float', 'double'      => 'float',
            'bool', 'boolean'      => 'bool',
            'array', 'json'        => 'array',
            default                => 'string',
        };

        return $nullable && $type !== 'array' ? '?'.$type : $type;
    }

    protected static function sqlType(string $sqlType, bool $nullable): string
    {
        $base = strtolower(preg_split('/[\s(]/', $sqlType)[0] ?? 'string');

        $type = match ($base) {
            'int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint' => 'int',
            'decimal', 'float', 'double', 'numeric' => 'float',
            'json' => 'array',
            'bool', 'boolean' => 'bool',
            default => 'string',
        };

        return $nullable && $type !== 'array' ? '?'.$type : $type;
    }

    protected static function inject(string $content, string $short, string $docblock): string
    {
        $docRegex = '\/\*\*[^*]*\*+(?:[^\/\*][^*]*\*+)*\/\s*';
        $pattern = '/(\s*)(?:'.$docRegex.')?((?:(?:abstract|final)\s+)?class\s+'.preg_quote($short, '/').'\b)/';

        return (string) preg_replace_callback(
            $pattern,
            static fn (array $m): string => "\n\n".$docblock."\n".$m[2],
            $content,
            1
        );
    }
}
