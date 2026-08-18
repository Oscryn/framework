<?php

namespace Oscryn\Exceptions;

use ErrorException;
use Throwable;

use const T_ABSTRACT, T_ARRAY, T_AS, T_ATTRIBUTE, T_BOOLEAN_AND, T_BOOLEAN_OR, T_BREAK, T_CASE;
use const T_CATCH, T_CLASS, T_CLONE, T_COALESCE, T_COALESCE_EQUAL, T_COMMENT, T_CONST, T_CONSTANT_ENCAPSED_STRING;
use const T_CONTINUE, T_DEC, T_DEFAULT, T_DNUMBER, T_DO, T_DOC_COMMENT, T_DOUBLE_ARROW, T_ECHO;
use const T_ELSE, T_ELSEIF, T_EMPTY, T_ENCAPSED_AND_WHITESPACE, T_ENDFOR, T_ENDFOREACH, T_ENDIF, T_ENDSWITCH;
use const T_ENDWHILE, T_ENUM, T_EXIT, T_EXTENDS, T_FINAL, T_FOR, T_FOREACH, T_FUNCTION;
use const T_GLOBAL, T_GOTO, T_IF, T_IMPLEMENTS, T_INC, T_INCLUDE, T_INCLUDE_ONCE, T_INSTANCEOF;
use const T_INTERFACE, T_ISSET, T_LIST, T_LNUMBER, T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR, T_MATCH;
use const T_NAMESPACE, T_NEW, T_NS_SEPARATOR, T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM;
use const T_POW, T_PRINT, T_PRIVATE, T_PROTECTED, T_PUBLIC, T_READONLY, T_REQUIRE, T_REQUIRE_ONCE;
use const T_RETURN, T_SPACESHIP, T_STATIC, T_SWITCH, T_THROW, T_TRAIT, T_TRY, T_USE;
use const T_VARIABLE, T_WHILE, T_YIELD;

class ErrorHandler
{
    protected static string $root = '';

    protected static bool $handling = false;

    public static function register(): void
    {
        static::$root = dirname(__DIR__, 3);

        ini_set('display_errors', '0');
        error_reporting(E_ALL);

        set_error_handler([static::class, 'handleError']);
        set_exception_handler([static::class, 'handleException']);
        register_shutdown_function([static::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        if (static::$handling) {
            fwrite(STDERR, 'Fatal error: '.$e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().PHP_EOL);
            exit(1);
        }

        static::$handling = true;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($e instanceof HttpException) {
            static::renderHttpError($e);
            return;
        }

        if (!app_env('local') && PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            static::log($e);
            static::renderGenericWeb();
            return;
        }

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            static::renderCli($e);
        } else {
            static::renderWeb($e);
        }
    }

    protected static function renderHttpError(HttpException $e): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            static::renderCli($e);
            return;
        }

        http_response_code($e->status());

        $hint = $e->hint() !== ''
            ? '<p class="mt-2 max-w-md text-sm text-subtext">'.htmlspecialchars($e->hint(), ENT_QUOTES, 'UTF-8').'</p>'
            : '';

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</title>'
            .static::styles()
            .'</head><body class="bg-base text-text antialiased text-sm font-sans leading-relaxed">'
            .'<div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">'
            .'<div class="pointer-events-none mb-8 select-none text-[120px] font-extrabold leading-none text-surface2">'.$e->status().'</div>'
            .'<h1 class="mb-2 text-2xl font-bold text-text">'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</h1>'
            .$hint
            .'<a href="/" class="mt-6 rounded-lg bg-blue px-4 py-2 text-sm font-bold text-base transition-transform hover:-translate-y-px">Back home</a>'
            .'</div></body></html>';

        exit(1);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            static::handleException(new ErrorException(
                $error['message'], 0, $error['type'], $error['file'], $error['line']
            ));
        }
    }

    protected static function renderCli(Throwable $e): void
    {
        $red    = "\033[38;5;203m";
        $white  = "\033[38;5;231m";
        $gray   = "\033[38;5;244m";
        $yellow = "\033[38;5;215m";
        $cyan   = "\033[38;5;117m";
        $reset  = "\033[0m";
        $line   = str_repeat('─', 72);

        fwrite(STDERR, $red.$line.$reset.PHP_EOL);
        fwrite(STDERR, $red.'ERROR'.$reset.'  '.$white.get_class($e).$reset.PHP_EOL);
        fwrite(STDERR, $white.$e->getMessage().$reset.PHP_EOL);
        fwrite(STDERR, $gray.static::relativePath($e->getFile()).':'.$e->getLine().$reset.PHP_EOL);
        fwrite(STDERR, $red.$line.$reset.PHP_EOL);

        foreach (static::sourceWindow($e->getFile(), $e->getLine()) as $num => $code) {
            $isError = $num === $e->getLine();
            fwrite(STDERR, ($isError ? $red.' ▶' : '  ').' '
                .$gray.str_pad((string) $num, 5, ' ', STR_PAD_LEFT).$reset.' '
                .($isError ? $red.$code.$reset : $gray.$code.$reset).PHP_EOL);
        }

        fwrite(STDERR, PHP_EOL);

        $frames = array_merge(
            [['file' => $e->getFile(), 'line' => $e->getLine(), 'function' => 'throw '.static::shortClass($e)]],
            $e->getTrace()
        );

        foreach ($frames as $i => $frame) {
            $location = isset($frame['file'])
                ? static::relativePath($frame['file']).':'.($frame['line'] ?? '?')
                : '[internal function]';

            fwrite(STDERR, $yellow.sprintf('#%-3d', $i).$reset.' '.$cyan.static::describeCall($frame).$reset.PHP_EOL);
            fwrite(STDERR, '      '.$gray.$location.$reset.PHP_EOL);
        }

        fwrite(STDERR, PHP_EOL);
    }

    protected static function renderWeb(Throwable $e): void
    {
        http_response_code(500);

        $class = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($e->getMessage() ?: 'No message provided.', ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars(static::relativePath($e->getFile()), ENT_QUOTES, 'UTF-8');

        $frames = '';
        foreach (static::frames($e) as $i => $frame) {
            $frames .= static::frameHtml($i, $frame);
        }

        $previous = '';
        $prev = $e->getPrevious();
        while ($prev !== null) {
            $previous .= static::previousHtml($prev);
            $prev = $prev->getPrevious();
        }

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.$class.'</title>'
            .static::styles()
            .'</head><body class="bg-base text-text antialiased text-sm font-sans leading-relaxed">'
            .'<header class="sticky top-0 z-10 flex items-center justify-between border-b border-surface2 bg-surface px-6 py-3.5 backdrop-blur">'
            .'<span class="font-bold tracking-wide text-blue">Oscryn</span>'
            .'<span class="rounded-full bg-red px-3 py-1 text-xs font-bold tracking-widest text-base">500 ERROR</span></header>'
            .'<main class="mx-auto max-w-[920px] px-6 pb-20 pt-10">'
            .'<section class="relative pb-8 pt-2">'
            .'<div class="pointer-events-none absolute -top-7 right-0 select-none text-[140px] font-extrabold leading-none text-surface2">500</div>'
            .'<h1 class="mb-2 text-xl font-bold text-red">'.$class.'</h1>'
            .'<p class="mb-2 text-base text-text">'.$message.'</p>'
            .'<p class="font-mono text-[13px] text-muted">'.$file.':'.$e->getLine().'</p>'
            .'</section>'
            .'<section class="mb-5 overflow-hidden rounded-xl border border-surface2 bg-surface shadow-[0_4px_20px_rgba(0,0,0,0.35)]"><div class="border-b border-surface2 bg-mantle px-4 py-3 text-xs font-bold uppercase tracking-widest text-blue">Source</div>'
            .'<pre class="m-0 overflow-x-auto px-0 py-3.5 font-mono text-[13px] leading-[1.7]">'.static::highlightWindow($e->getFile(), $e->getLine()).'</pre></section>'
            .'<section class="mb-5 overflow-hidden rounded-xl border border-surface2 bg-surface shadow-[0_4px_20px_rgba(0,0,0,0.35)]"><div class="border-b border-surface2 bg-mantle px-4 py-3 text-xs font-bold uppercase tracking-widest text-blue">Stack Trace</div>'.$frames.'</section>'
            .$previous
            .'</main></body></html>';

        exit(1);
    }

    protected static function renderGenericWeb(): void
    {
        http_response_code(500);

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Oscryn &middot; 500</title>'
            .static::styles()
            .'</head><body class="bg-base text-text antialiased text-sm font-sans leading-relaxed">'
            .'<div class="flex min-h-screen flex-col items-center justify-center px-6 text-center">'
            .'<div class="pointer-events-none mb-8 select-none text-[120px] font-extrabold leading-none text-surface2">500</div>'
            .'<h1 class="mb-2 text-2xl font-bold text-text">Something went wrong.</h1>'
            .'<p class="max-w-md text-subtext">An unexpected error occurred. The details have been logged and we&rsquo;re on it.</p>'
            .'</div></body></html>';

        exit(1);
    }

    protected static function styles(): string
    {
        return <<<'CSS'
<style>
:root{
    --base:#1e1e2e;--mantle:#181825;--surface:#313244;--surface2:#585b70;
    --text:#cdd6f4;--subtext:#a6adc8;--muted:#6c7086;
    --blue:#89b4fa;--green:#a6e3a1;--red:#f38ba8;--peach:#fab387;--yellow:#f9e2af;--mauve:#cba6f7;
}
*{box-sizing:border-box}
html{background:var(--base)}
body{margin:0;background:var(--base);color:var(--text);font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;font-size:14px;line-height:1.6}
a{text-decoration:none}
.font-sans{font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
.font-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,'Liberation Mono',monospace}
.bg-base{background:var(--base)}
.bg-surface{background:var(--surface)}
.bg-surface2{background:var(--surface2)}
.bg-mantle{background:var(--mantle)}
.bg-red{background:var(--red)}
.bg-blue{background:var(--blue)}
.text-text{color:var(--text)}
.text-subtext{color:var(--subtext)}
.text-muted{color:var(--muted)}
.text-blue{color:var(--blue)}
.text-red{color:var(--red)}
.text-peach{color:var(--peach)}
.text-base{color:var(--base)}
.text-surface2{color:var(--surface2)}
.text-sm{font-size:14px}
.text-xs{font-size:12px}
.text-xl{font-size:20px}
.text-2xl{font-size:24px}
.text-\[13px\]{font-size:13px}
.text-\[120px\]{font-size:120px}
.text-\[140px\]{font-size:140px}
.leading-relaxed{line-height:1.6}
.leading-none{line-height:1}
.font-bold{font-weight:700}
.font-extrabold{font-weight:800}
.tracking-wide{letter-spacing:.025em}
.tracking-widest{letter-spacing:.1em}
.uppercase{text-transform:uppercase}
.flex{display:flex}
.flex-col{flex-direction:column}
.items-center{align-items:center}
.justify-center{justify-content:center}
.justify-between{justify-content:space-between}
.min-h-screen{min-height:100vh}
.text-center{text-align:center}
.sticky{position:sticky}
.top-0{top:0}
.z-10{z-index:10}
.relative{position:relative}
.absolute{position:absolute}
.-top-7{top:-28px}
.right-0{right:0}
.px-0{padding-left:0;padding-right:0}
.px-3{padding-left:12px;padding-right:12px}
.px-4{padding-left:16px;padding-right:16px}
.px-6{padding-left:24px;padding-right:24px}
.py-0\.5{padding-top:2px;padding-bottom:2px}
.py-1{padding-top:4px;padding-bottom:4px}
.py-2{padding-top:8px;padding-bottom:8px}
.py-3{padding-top:12px;padding-bottom:12px}
.py-3\.5{padding-top:14px;padding-bottom:14px}
.pt-2{padding-top:8px}
.pt-10{padding-top:40px}
.pb-3{padding-bottom:12px}
.pb-8{padding-bottom:32px}
.pb-20{padding-bottom:80px}
.m-0{margin:0}
.mt-6{margin-top:24px}
.mb-2{margin-bottom:8px}
.mb-5{margin-bottom:20px}
.mb-8{margin-bottom:32px}
.mx-auto{margin-left:auto;margin-right:auto}
.ml-auto{margin-left:auto}
.rounded-full{border-radius:9999px}
.rounded-lg{border-radius:8px}
.rounded-xl{border-radius:12px}
.border-b{border-bottom:1px solid}
.border-surface2{border-color:var(--surface2)}
.shadow-\[0_4px_20px_rgba\(0\,0\,0\,0\.35\)\]{box-shadow:0 4px 20px rgba(0,0,0,.35)}
.overflow-hidden{overflow:hidden}
.overflow-x-auto{overflow-x:auto}
.whitespace-nowrap{white-space:nowrap}
.text-ellipsis{text-overflow:ellipsis}
.flex-none{flex:none}
.gap-3\.5{gap:14px}
.cursor-pointer{cursor:pointer}
.pointer-events-none{pointer-events:none}
.select-none{user-select:none}
.backdrop-blur{-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}
.transition-transform{transition:transform .15s ease}
.max-w-md{max-width:28rem}
.max-w-\[920px\]{max-width:920px}
.last\:border-0:last-child{border-bottom:0}
.hover\:-translate-y-px:hover{transform:translateY(-1px)}
.code-line{display:block;padding:0 16px}
.code-line.error{background:rgba(243,139,168,.1);box-shadow:inset 3px 0 0 var(--red)}
.line-num{display:inline-block;width:3.5em;padding-right:16px;color:var(--muted);text-align:right;user-select:none}
.line-code{white-space:pre}
.t-plain{color:var(--text)}
.t-variable{color:#94e2d5}
.t-comment{color:var(--muted);font-style:italic}
.t-number{color:var(--blue)}
.t-string{color:var(--green)}
.t-keyword{color:var(--yellow)}
.t-operator{color:var(--mauve)}
</style>
CSS;
    }

    protected static function log(Throwable $e): void
    {
        $dir = rtrim(static::$root, '/').'/storage/logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $line = '['.date('Y-m-d H:i:s').'] '.get_class($e).': '.$e->getMessage()
            .' in '.$e->getFile().':'.$e->getLine().PHP_EOL
            .$e->getTraceAsString().PHP_EOL;

        file_put_contents($dir.'/oscryn.log', $line, FILE_APPEND);
    }

    protected static function frames(Throwable $e): array
    {
        return array_merge(
            [['file' => $e->getFile(), 'line' => $e->getLine(), 'function' => 'throw '.static::shortClass($e)]],
            $e->getTrace()
        );
    }

    protected static function frameHtml(int $index, array $frame): string
    {
        $file = $frame['file'] ?? null;
        $line = $frame['line'] ?? null;
        $location = $file !== null
            ? htmlspecialchars(static::relativePath($file), ENT_QUOTES, 'UTF-8').':'.($line ?? '?')
            : '[internal function]';
        $call = htmlspecialchars(static::describeCall($frame), ENT_QUOTES, 'UTF-8');
        $args = '';

        if (isset($frame['args']) && $frame['args'] !== []) {
            $items = '';
            foreach ($frame['args'] as $arg) {
                $items .= '<div class="px-4 py-0.5 font-mono text-xs text-subtext"><span>'
                    .htmlspecialchars(static::describeValue($arg), ENT_QUOTES, 'UTF-8').'</span></div>';
            }
            $args = '<details><summary class="cursor-pointer px-4 py-2 text-xs text-blue">View arguments ('.count($frame['args']).')</summary>'
                .$items.'</details>';
        }

        return '<div class="border-b border-surface2 last:border-0"><div class="flex items-center gap-3.5 px-4 py-3">'
            .'<span class="flex-none font-mono text-muted">#'.$index.'</span>'
            .'<span class="overflow-hidden text-ellipsis whitespace-nowrap font-mono text-[13px] text-text">'.$call.'</span>'
            .'<span class="ml-auto flex-none font-mono text-xs text-muted">'.$location.'</span>'
            .'</div>'.$args.'</div>';
    }

    protected static function previousHtml(Throwable $e): string
    {
        $class = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars(static::relativePath($e->getFile()), ENT_QUOTES, 'UTF-8');

        return '<section class="mb-5 overflow-hidden rounded-xl border border-surface2 bg-surface shadow-[0_4px_20px_rgba(0,0,0,0.35)]">'
            .'<div class="border-b border-surface2 bg-mantle px-4 py-3 text-xs font-bold uppercase tracking-widest text-peach">Previous: '.$class.'</div>'
            .'<p class="px-4 py-2 text-base text-text">'.$message.'</p>'
            .'<p class="px-4 pb-3 font-mono text-[13px] text-muted">'.$file.':'.$e->getLine().'</p></section>';
    }

    protected static function describeCall(array $frame): string
    {
        $function = $frame['function'] ?? '{closure}';

        if (isset($frame['class'])) {
            return static::shortClass($frame['class']).($frame['type'] ?? '->').$function.'()';
        }

        return $function.'()';
    }

    protected static function describeValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return strlen($value) > 48
                ? "'".substr($value, 0, 45)."...'"
                : "'".$value."'";
        }

        if (is_array($value)) {
            return 'array('.count($value).')';
        }

        if (is_object($value)) {
            return static::shortClass($value).' {…}';
        }

        if (is_resource($value)) {
            return 'resource('.get_resource_type($value).')';
        }

        return get_debug_type($value);
    }

    protected static function shortClass(object|string $class): string
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    protected static function relativePath(string $path): string
    {
        $root = rtrim(static::$root, '/').'/';

        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }

        return basename($path);
    }

    protected static function sourceWindow(string $file, int $errorLine, int $around = 6): array
    {
        $lines = [];

        if (!is_file($file)) {
            return $lines;
        }

        $source = file($file) ?: [];

        for ($num = max(1, $errorLine - $around); $num <= $errorLine + $around; $num++) {
            if (isset($source[$num - 1])) {
                $lines[$num] = rtrim($source[$num - 1], "\r\n");
            }
        }

        return $lines;
    }

    protected static function highlightWindow(string $file, int $errorLine, int $around = 6): string
    {
        $highlighted = static::highlightFile($file);
        $start = max(1, $errorLine - $around);
        $end = $errorLine + $around;
        $html = '';

        foreach ($highlighted as $num => $code) {
            if ($num < $start || $num > $end) {
                continue;
            }

            $html .= '<div class="code-line'.($num === $errorLine ? ' error' : '').'">'
                .'<span class="line-num">'.str_pad((string) $num, 4, ' ', STR_PAD_LEFT).'</span>'
                .'<span class="line-code">'.($code === '' ? ' ' : $code).'</span>'
                .'</div>'."\n";
        }

        return $html;
    }

    protected static function highlightFile(string $file): array
    {
        $lines = [];

        if (!is_file($file)) {
            return $lines;
        }

        $tokens = token_get_all((string) file_get_contents($file));
        $current = 1;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $text = htmlspecialchars($token[1], ENT_NOQUOTES, 'UTF-8');
                $class = static::tokenClass($token[0]);
            } else {
                $text = htmlspecialchars($token, ENT_NOQUOTES, 'UTF-8');
                $class = 'plain';
            }

            $parts = explode("\n", $text);

            foreach ($parts as $i => $part) {
                $lines[$current] = ($lines[$current] ?? '');

                if ($part !== '') {
                    $lines[$current] .= '<span class="t-'.$class.'">'.$part.'</span>';
                }

                if ($i < count($parts) - 1) {
                    $current++;
                }
            }
        }

        return $lines;
    }

    protected static function tokenClass(int $id): string
    {
        return match ($id) {
            T_VARIABLE => 'variable',
            T_COMMENT, T_DOC_COMMENT => 'comment',
            T_LNUMBER, T_DNUMBER => 'number',
            T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE => 'string',
            T_IF, T_ELSE, T_ELSEIF, T_ENDIF, T_FOR, T_ENDFOR, T_FOREACH, T_ENDFOREACH, T_WHILE,
            T_ENDWHILE, T_DO, T_SWITCH, T_ENDSWITCH, T_CASE, T_DEFAULT, T_BREAK, T_CONTINUE,
            T_RETURN, T_FUNCTION, T_CLASS, T_NEW, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC,
            T_ABSTRACT, T_FINAL, T_USE, T_NAMESPACE, T_ECHO, T_CONST, T_EXTENDS, T_IMPLEMENTS,
            T_INTERFACE, T_TRAIT, T_TRY, T_CATCH, T_THROW, T_AS, T_ARRAY, T_INSTANCEOF,
            T_EXIT, T_LIST, T_PRINT, T_CLONE, T_GLOBAL, T_ISSET, T_EMPTY,
            T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_MATCH, T_ENUM, T_READONLY,
            T_YIELD, T_GOTO, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR,
            T_LOGICAL_XOR, T_COALESCE, T_COALESCE_EQUAL, T_SPACESHIP, T_POW, T_INC, T_DEC => 'keyword',
            T_OBJECT_OPERATOR, T_DOUBLE_ARROW, T_PAAMAYIM_NEKUDOTAYIM, T_NS_SEPARATOR,
            T_NULLSAFE_OBJECT_OPERATOR, T_ATTRIBUTE => 'operator',
            default => 'plain',
        };
    }
}
