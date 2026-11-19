<?php

namespace App\Services\SystemLog\Domain\Access;

/**
 * قواعد دسترسی به خطاها (خالص، بدون دیتابیس و config):
 *   «واجد شرایط» = اپراتور فعال + is_admin + شعبه‌ی مدیریت در branch او (یا branch=[0] یعنی همه‌ی شعبه‌ها).
 *   «می‌بیند» = واجد شرایط + با دامنه‌ی شعبه‌ی مدیریت وارد شده + ردیف فعال در جدول دسترسی (در هر درخواست دوباره چک می‌شود).
 *   مدیر (is_manager در همان جدول) همه‌ی دسته‌ها را می‌بیند و فقط او دسترسی می‌دهد.
 * خطاها از همه‌ی شعبه‌ها دیده می‌شوند، ولی فقط در دسته‌های کاربر. اعلان فقط از «یک» کانال (AlertChannel) می‌رود.
 */
final class AccessPolicy
{
    public function __construct(private readonly int $managerBranchId = 1) {}

    public function eligible(AccessOperator $operator): bool
    {
        return $operator->isActive
            && $operator->isAdmin
            && ($operator->branches === null || in_array($this->managerBranchId, $operator->branches, true));
    }

    /** محدوده‌ی دید این اپراتور؛ null = هیچ دسترسی */
    public function scopeFor(AccessOperator $operator, ?Grant $grant, ?int $requestBranchId): ?AccessScope
    {
        if ($grant === null || ! $grant->isActive || $requestBranchId !== $this->managerBranchId || ! $this->eligible($operator)) {
            return null;
        }

        return new AccessScope($grant->all || $grant->isManager, $grant->categoryIds, $grant->isManager);
    }

    /**
     * گیرنده‌های اعلان یک خطای یکتای جدید: دسترسی فعال با کانال تعیین‌شده، اپراتور واجد شرایط با موبایل،
     * و خطا در دسته‌هایش (منبع یا نوع) باشد. شعبه‌ی request اینجا معنا ندارد (اعلان از جاب است).
     *
     * @param  array<int, array{0: Grant, 1: AccessOperator}>  $grantsWithOperators
     * @return array<int, array{operator_id: int, mobile: string, channel: AlertChannel}>
     */
    public function recipients(array $grantsWithOperators, int $sourceId, int $typeId): array
    {
        $recipients = [];

        foreach ($grantsWithOperators as [$grant, $operator]) {
            $scope = new AccessScope($grant->all || $grant->isManager, $grant->categoryIds, $grant->isManager);

            if ($grant->isActive && $grant->channel !== null && ($operator->mobile ?? '') !== '' && $this->eligible($operator) && $scope->canSee($sourceId, $typeId)) {
                $recipients[] = ['operator_id' => $operator->id, 'mobile' => $operator->mobile, 'channel' => $grant->channel];
            }
        }

        return $recipients;
    }

    /**
     * اعتبارسنجی تنظیم یک دسترسی: اپراتور هدف واجد شرایط باشد و دسته‌ها وجود داشته باشند.
     * (کانال با enum AlertChannel خودش معتبر است.)
     *
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $knownCategoryIds  شناسه‌ی همه‌ی دسته‌های موجود
     */
    public function validateGrant(AccessOperator $target, array $categoryIds, array $knownCategoryIds): void
    {
        if (! $this->eligible($target)) {
            throw AccessException::invalid('system_log.access.operator_not_eligible');
        }

        if ($categoryIds === [] || array_diff($categoryIds, $knownCategoryIds) !== []) {
            throw AccessException::invalid('system_log.access.category_invalid');
        }
    }
}
