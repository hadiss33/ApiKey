<?php

namespace App\Services\SystemLog\Application\DTOs;

/**
 * یک صفحه از یک لیست.
 *
 * @template T
 */
final class Page
{
    /** @param  array<int, T>  $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
