<?php

namespace App\Services\SystemLog\Domain\Access;

/**
 * «محدوده‌ی دیدِ» یک اپراتور روی خطاها (نتیجه‌ی AccessPolicy::scopeFor که Store روی کوئری اعمال می‌کند).
 * بدون دسترسی scope ساخته نمی‌شود (null). all = همه‌ی دسته‌ها؛ وگرنه خطا دیده می‌شود اگر منبع «یا» نوعش در categoryIds باشد.
 */
final class AccessScope
{
    /** @param  array<int, int>  $categoryIds */
    public function __construct(
        public readonly bool $all,
        public readonly array $categoryIds,
        public readonly bool $isManager,
    ) {}

    public function canSee(int $sourceId, int $typeId): bool
    {
        return $this->all || in_array($sourceId, $this->categoryIds, true) || in_array($typeId, $this->categoryIds, true);
    }
}
