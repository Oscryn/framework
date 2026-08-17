<?php

namespace Oscryn\Database;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

class Paginator implements IteratorAggregate, Countable, JsonSerializable
{
    public function __construct(
        protected array $items,
        protected int $total,
        protected int $perPage,
        protected int $currentPage,
    ) {
    }

    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function previousPage(): ?int
    {
        return $this->currentPage > 1 ? $this->currentPage - 1 : null;
    }

    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    public function links(): string
    {
        if (!$this->hasPages()) {
            return '';
        }

        $html = '<div class="oscryn-pagination" style="display:flex;align-items:center;gap:8px;'
            .'font-family:ui-sans-serif,system-ui,sans-serif;font-size:14px;color:#cdd6f4;'
            .'background:#313244;padding:10px 14px;border-radius:8px;border:1px solid #585b70;'
            .'width:fit-content;margin:20px auto;">';

        $html .= $this->link($this->previousPage(), '‹ Previous');

        for ($page = 1; $page <= $this->lastPage(); $page++) {
            $html .= $page === $this->currentPage
                ? '<span style="padding:4px 10px;border-radius:6px;background:#89b4fa;color:#1e1e2e;font-weight:700;">'.$page.'</span>'
                : $this->link($page, (string) $page);
        }

        $html .= $this->link($this->nextPage(), 'Next ›');

        return $html.'</div>';
    }

    protected function link(?int $page, string $label): string
    {
        if ($page === null) {
            return '<span style="padding:4px 10px;opacity:.45;">'.$label.'</span>';
        }

        return '<a href="'.htmlspecialchars($this->url($page), ENT_QUOTES, 'UTF-8').'" '
            .'style="padding:4px 10px;border-radius:6px;color:#cdd6f4;text-decoration:none;'
            .'border:1px solid #585b70;">'.$label.'</a>';
    }

    protected function url(int $page): string
    {
        $query = $_GET;
        $query['page'] = $page;

        return '?'.http_build_query($query);
    }

    public function toArray(): array
    {
        return [
            'data'         => $this->items,
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage(),
        ];
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
