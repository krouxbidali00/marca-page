<?php

namespace App\Pagination;

/**
 * @template T
 */
final readonly class Page
{
    /** @param T[] $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pageCount();
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function firstIndex(): int
    {
        return $this->total === 0 ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastIndex(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    /** @return int[] */
    public function pageRange(int $around = 2): array
    {
        $from = max(1, $this->page - $around);
        $to = min($this->pageCount(), $this->page + $around);

        return range($from, $to);
    }
}
