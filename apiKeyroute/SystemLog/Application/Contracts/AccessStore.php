<?php

namespace App\Services\SystemLog\Application\Contracts;

use App\Services\SystemLog\Domain\Access\AccessOperator;
use App\Services\SystemLog\Domain\Access\AlertChannel;
use App\Services\SystemLog\Domain\Access\Grant;

/** پورت دسترسی به خطاها و خواندن اپراتورها (پیاده‌سازی: EloquentAccessStore؛ اپراتور فقط خوانده می‌شود). */
interface AccessStore
{
    public function operator(int $id): ?AccessOperator;

    public function grant(int $operatorId): ?Grant;

    /** @return array<int, Grant> */
    public function grants(): array;

    /**
     * ساخت یا جایگزینی کامل دسترسی یک اپراتور (یک ردیف به‌ازای هر اپراتور).
     *
     * @param  array<int, int>  $categoryIds
     */
    public function save(int $operatorId, bool $isManager, bool $isActive, array $categoryIds, ?AlertChannel $channel, int $actorId): Grant;

    public function delete(int $operatorId): bool;

    /**
     * دسترسی‌های «فعال» همراه اپراتورهای «فعال»شان (برای پیدا کردن گیرنده‌های اعلان).
     *
     * @return array<int, array{0: Grant, 1: AccessOperator}>
     */
    public function activeGrants(): array;
}
