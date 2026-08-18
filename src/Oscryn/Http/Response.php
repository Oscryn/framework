<?php

namespace Oscryn\Http;

use JsonSerializable;

class Response
{
    protected string $content;
    protected int $status;
    protected array $headers;

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function from(mixed $value): static
    {
        if ($value instanceof static) {
            return $value;
        }

        if (is_array($value) || $value instanceof JsonSerializable) {
            return static::json($value);
        }

        return new static((string) $value);
    }

    public static function json(mixed $data, int $status = 200): static
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $json = json_encode(['error' => 'Failed to encode response as JSON']);
        }

        if (static::browserWantsHtml()) {
            return new static(
                static::jsonViewer($json, $status),
                $status,
                ['Content-Type' => 'text/html; charset=utf-8']
            );
        }

        return new static(
            $json,
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    protected static function browserWantsHtml(): bool
    {
        if (!app_env('local')) {
            return false;
        }

        $server = $_SERVER;

        if (($server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return false;
        }

        if (str_contains($server['HTTP_ACCEPT'] ?? '', 'application/json')) {
            return false;
        }

        $secFetchMode = $server['HTTP_SEC_FETCH_MODE'] ?? null;
        if ($secFetchMode !== null && $secFetchMode !== 'navigate') {
            return false;
        }

        if (isset($_GET['raw'])) {
            return false;
        }

        return str_contains($server['HTTP_ACCEPT'] ?? '', 'text/html');
    }

    protected static function jsonViewer(string $json, int $status): string
    {
        $uri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
        $badge = $status >= 400 ? 'bg-red' : 'bg-green';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>JSON Response</title>'
            .'<link rel="stylesheet" href="/css/app.css">'
            .'</head><body class="bg-base text-text antialiased text-sm font-sans leading-relaxed">'
            .'<header class="sticky top-0 z-10 flex items-center justify-between border-b border-surface2 bg-surface px-6 py-3.5 backdrop-blur">'
            .'<span class="font-bold tracking-wide text-blue">Oscryn</span>'
            .'<span class="rounded-full '.$badge.' px-3 py-1 text-xs font-bold tracking-widest text-base">'.htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8').'</span></header>'
            .'<main class="mx-auto max-w-[920px] px-6 pb-20 pt-8">'
            .'<div class="mb-3.5 flex items-center gap-3">'
            .'<span class="rounded-md bg-surface2 px-2.5 py-0.5 text-[11px] font-extrabold tracking-widest text-blue">GET</span>'
            .'<span class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap font-mono text-[13px] text-subtext">'.$uri.'</span>'
            .'<button id="copy" type="button" class="cursor-pointer rounded-lg bg-blue px-4 py-1.5 text-xs font-bold text-base transition-transform hover:-translate-y-px">Copy</button></div>'
            .'<pre id="json-view" class="m-0 overflow-x-auto whitespace-pre rounded-xl border border-surface2 bg-surface p-6 font-mono text-[13px] leading-[1.8] text-text shadow-[0_8px_30px_rgba(0,0,0,0.35)]">'.static::highlightJson($json).'</pre>'
            .'</main>'
            .'<script>'
            .'const btn=document.getElementById("copy"),view=document.getElementById("json-view");'
            .'btn.addEventListener("click",()=>{navigator.clipboard.writeText(view.innerText).then(()=>{'
            .'const t=btn.textContent;btn.textContent="Copied!";setTimeout(()=>btn.textContent=t,1200);});});'
            .'</script>'
            .'</body></html>';
    }

    protected static function highlightJson(string $json): string
    {
        $pattern = '/(?<key>"(?:\\\\.|[^"\\\\])*")(?=\s*:)|(?<string>"(?:\\\\.|[^"\\\\])*")|(?<number>-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)|(?<bool>\btrue\b|\bfalse\b|\bnull\b)/';

        return preg_replace_callback($pattern, static function (array $matches): string {
            $text = htmlspecialchars($matches[0], ENT_NOQUOTES, 'UTF-8');

            if ($matches['key'] !== '') {
                return '<span class="j-key">'.$text.'</span>';
            }

            if ($matches['string'] !== '') {
                return '<span class="j-string">'.$text.'</span>';
            }

            if ($matches['number'] !== '') {
                return '<span class="j-number">'.$text.'</span>';
            }

            return '<span class="j-bool">'.$text.'</span>';
        }, $json);
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function redirect(string $location, int $status = 302): static
    {
        $this->status = $status;
        $this->headers['Location'] = $location;

        return $this;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name.': '.$value);
            }
        }

        echo $this->content;
    }
}
