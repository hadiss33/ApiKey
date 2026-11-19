<?php

namespace App\Services\SystemLog\Domain\Access;

/**
 * دسترسی یک اپراتور به خطاها (یک ردیف در system_log_access).
 * categoryIds: شناسه‌ی دسته‌هایی که می‌بیند (source و type)؛ all = دسته‌ی ویژه‌ی «همه» داده شده؛ isManager = مدیر.
 * channel: تنها کانالی که اعلان خطای جدید از آن می‌رود؛ null = اعلان نمی‌گیرد. مدیر تعیین می‌کند.
 */
final class Grant
{
    /** @param  array<int, int>  $categoryIds */
    public function __construct(
        public readonly int $id,
        public readonly int $operatorId,
        public readonly bool $isManager,
        public readonly bool $isActive,
        public readonly bool $all,
        public readonly array $categoryIds,
        public readonly ?AlertChannel $channel,
    ) {}
}
