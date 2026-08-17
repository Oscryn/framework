<?php

namespace Oscryn\Support;

use ReflectionObject;

class Dumper
{
    public static function dump(string $label, mixed ...$values): mixed
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $location = ($trace['file'] ?? 'unknown').':'.($trace['line'] ?? 0);
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

        $ansi = [
            'string'  => '38;5;114',
            'number'  => '38;5;117',
            'keyword' => '38;5;215',
            'key'     => '38;5;141',
            'class'   => '38;5;141;1',
            'punct'   => '90',
            'muted'   => '90',
        ];

        $html = [
            'string'  => '#a6e3a1',
            'number'  => '#89b4fa',
            'keyword' => '#f9e2af',
            'key'     => '#cba6f7',
            'class'   => '#cba6f7',
            'punct'   => '#6c7086',
            'muted'   => '#6c7086',
        ];

        $esc = static fn (string $text): string => $isCli ? $text : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $paint = static function (string $kind, string $text) use ($isCli, $ansi, $html): string {
            return $isCli
                ? "\033[{$ansi[$kind]}m".$text."\033[0m"
                : '<span style="color:'.$html[$kind].'">'.$text.'</span>';
        };

        $details = static function (string $summary, string $body) use ($isCli): string {
            if ($isCli) {
                return $summary."\n".$body;
            }

            return '<details open><summary style="cursor:pointer;color:inherit;">'
                .$summary.'</summary>'.$body.'</details>';
        };

        $render = null;
        $render = static function (mixed $value, int $depth = 0) use (&$render, $paint, $esc, $details): string {
            if ($depth > 8) {
                return $paint('muted', '…');
            }

            if (is_string($value)) {
                return $paint('string', "'".$esc($value)."'");
            }

            if (is_int($value) || is_float($value)) {
                return $paint('number', (string) $value);
            }

            if (is_bool($value)) {
                return $paint('keyword', $value ? 'true' : 'false');
            }

            if ($value === null) {
                return $paint('keyword', 'null');
            }

            if (is_array($value)) {
                if ($value === []) {
                    return $paint('punct', '[]');
                }

                $pad = str_repeat('  ', $depth + 1);
                $lines = [];

                foreach ($value as $key => $item) {
                    $lines[] = $pad.$paint('key', (string) $key).$paint('punct', ' => ').$render($item, $depth + 1);
                }

                $summary = $paint('punct', 'array (')
                    .$paint('number', (string) count($value))
                    .$paint('punct', ')');
                $body = implode("\n", $lines);

                return $details($summary, $body);
            }

            if (is_object($value)) {
                $pad = str_repeat('  ', $depth + 1);
                $lines = [];

                foreach ((new ReflectionObject($value))->getProperties() as $property) {
                    if ($property->isStatic()) {
                        continue;
                    }

                    $initialized = $property->isInitialized($value);
                    $propertyValue = $initialized
                        ? $render($property->getValue($value), $depth + 1)
                        : $paint('muted', 'uninitialized');

                    $lines[] = $pad.$paint('key', $property->getName()).$paint('punct', ' => ').$propertyValue;
                }

                $summary = $paint('class', $value::class).' '.$paint('punct', '{');
                $body = implode("\n", $lines)."\n".str_repeat('  ', $depth).$paint('punct', '}');

                return $details($summary, $body);
            }

            if (is_resource($value)) {
                return $paint('muted', get_resource_type($value).' resource');
            }

            return $paint('muted', get_debug_type($value));
        };

        foreach ($values as $value) {
            if ($isCli) {
                echo "\033[38;5;214m{$label}()\033[0m \033[90m{$location}\033[0m\n";
                echo "\033[90m".str_repeat('─', 72)."\033[0m\n";
                echo $render($value)."\n";
            } else {
                echo '<div style="background:#1e1e2e;color:#cdd6f4;padding:16px 20px;border-radius:10px;'
                    .'font:13px/1.7 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre;'
                    .'overflow-x:auto;box-shadow:0 2px 8px rgba(0,0,0,.2);margin:0 0 12px;">';
                echo '<span style="color:#f38ba8;font-weight:700">'.$label.'()</span> '
                    .'<span style="color:#7f849c">'.htmlspecialchars($location, ENT_QUOTES, 'UTF-8').'</span>'."\n";
                echo $render($value)."\n";
                echo '</div>';
            }
        }

        return $values[0] ?? null;
    }
}
