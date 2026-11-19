<?php

namespace App\Services\SystemLog\Domain\Access;

/**
 * نمای حداقلی یک اپراتور برای تصمیم دسترسی. branches: null = «همه‌ی شعبه‌ها» (branch=[0])؛ لیست خالی = هیچ شعبه.
 */
final class AccessOperator
{
    /** @param  array<int, int>|null  $branches */
    public function __construct(
        public readonly int $id,
        public readonly bool $isAdmin,
        public readonly bool $isActive,
        public readonly ?array $branches,
        public readonly ?string $mobile,
    ) {}
}
