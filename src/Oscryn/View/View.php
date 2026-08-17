<?php

namespace Oscryn\View;

use Latte\Engine;
use Oscryn\Http\Csrf;
use Oscryn\Http\Response;

class View
{
    protected static string $path = '';
    protected static string $cachePath = '';
    protected static bool $configured = false;

    public static function configure(string $path, ?string $cachePath = null): void
    {
        static::$path = rtrim($path, '/');
        static::$cachePath = rtrim($cachePath ?? sys_get_temp_dir().'/latte', '/');
        static::$configured = false;
    }

    public static function render(string $template, array $data = []): string
    {
        return static::engine()->renderToString(static::$path.'/'.$template.'.latte', $data);
    }

    public static function make(string $template, array $data = [], int $status = 200): Response
    {
        return new Response(
            static::render($template, $data),
            $status,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    protected static function engine(): Engine
    {
        if (!static::$configured) {
            if (!is_dir(static::$cachePath)) {
                mkdir(static::$cachePath, 0777, true);
            }

            static::$configured = true;
        }

        $engine = new Engine();
        $engine->setTempDirectory(static::$cachePath);
        $engine->setAutoRefresh(true);

        $engine->addFunction('csrf_field', static fn (): string => Csrf::field());
        $engine->addFunction('csrf_token', static fn (): string => Csrf::token());

        return $engine;
    }
}
