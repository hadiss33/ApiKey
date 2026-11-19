<?php

namespace App\Services\SystemLog\Application\Services;

use App\Services\SystemLog\Application\Contracts\AccessStore;
use App\Services\SystemLog\Domain\Access\AccessException;
use App\Services\SystemLog\Domain\Access\AccessPolicy;
use App\Services\SystemLog\Domain\Access\AccessScope;
use App\Services\SystemLog\Domain\Access\AlertChannel;
use App\Services\SystemLog\Domain\Access\Grant;
use App\Services\SystemLog\Domain\Error\ErrorTaxonomy;

/**
 * دسترسی به خطاها. قاعده‌ها در AccessPolicy (دامنه) است؛ اینجا فقط خواندن از Store و هماهنگی.
 * «شعبه‌ی request» همیشه از فراخوان می‌آید: دیدن و تنظیم فقط با دامنه‌ی شعبه‌ی مدیریت ممکن است. فقط مدیر دسترسی می‌دهد.
 */
final class AccessService
{
    public function __construct(
        private readonly AccessStore $store,
        private readonly AccessPolicy $policy,
        private readonly CategoryService $categories,
    ) {}

    /** محدوده‌ی دید این اپراتور؛ null = هیچ دسترسی */
    public function scopeFor(int $operatorId, ?int $requestBranchId): ?AccessScope
    {
        $operator = $this->store->operator($operatorId);

        return $operator === null ? null : $this->policy->scopeFor($operator, $this->store->grant($operatorId), $requestBranchId);
    }

    public function requireScope(int $operatorId, ?int $requestBranchId): AccessScope
    {
        return $this->scopeFor($operatorId, $requestBranchId) ?? throw AccessException::forbidden('system_log.access.denied');
    }

    public function requireManager(int $operatorId, ?int $requestBranchId): AccessScope
    {
        $scope = $this->scopeFor($operatorId, $requestBranchId);

        return $scope !== null && $scope->isManager ? $scope : throw AccessException::forbidden('system_log.access.manager_only');
    }

    /** @return array<int, Grant> */
    public function grants(int $managerId, ?int $branchId): array
    {
        $this->requireManager($managerId, $branchId);

        return $this->store->grants();
    }

    /**
     * ساخت/جایگزینی کامل دسترسی یک اپراتور (فقط مدیر). مدیر همیشه «همه» را دارد. channel = تنها کانال اعلان (null = بدون اعلان).
     *
     * @param  array<int, int>  $categoryIds
     */
    public function save(int $managerId, ?int $branchId, int $operatorId, bool $isManager, bool $isActive, array $categoryIds, ?AlertChannel $channel): Grant
    {
        $this->requireManager($managerId, $branchId);

        $target = $this->store->operator($operatorId) ?? throw AccessException::invalid('system_log.access.operator_not_found');
        $tax = $this->categories->taxonomy();
        $categoryIds = $isManager ? [$tax->id(ErrorTaxonomy::ALL, 'all')] : array_values(array_unique($categoryIds));

        $this->policy->validateGrant($target, $categoryIds, array_column($tax->all(), 'id'));

        return $this->store->save($operatorId, $isManager, $isActive, $categoryIds, $channel, $managerId);
    }

    public function revoke(int $managerId, ?int $branchId, int $operatorId): bool
    {
        $this->requireManager($managerId, $branchId);

        // جلوگیری از قفل شدن: مدیر ردیف خودش را حذف نمی‌کند
        if ($operatorId === $managerId) {
            throw AccessException::invalid('system_log.access.cannot_remove_self');
        }

        return $this->store->delete($operatorId);
    }

    /**
     * گیرنده‌های اعلان یک خطا.
     *
     * @return array<int, array{operator_id: int, mobile: string, channel: AlertChannel}>
     */
    public function recipientsFor(int $sourceId, int $typeId): array
    {
        return $this->policy->recipients($this->store->activeGrants(), $sourceId, $typeId);
    }
}
