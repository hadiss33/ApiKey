<?php

namespace App\Services\ApiKey\Application\Contracts;

use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use DateTimeInterface;

/**
 * پورت ذخیره‌ی کلیدها. پیاده‌سازی: EloquentApiKeyStore (که hash کلید را هم انجام می‌دهد).
 * همه‌ی متدهای خواندن/نوشتن پنل شعبه‌دارند؛ کلید شعبه‌ی دیگر «نبود» حساب می‌شود.
 */
interface ApiKeyStore
{
    /** hash یک‌طرفه‌ی کلید خام (HMAC-SHA256 با کلید مخفی سرور) */
    public function hash(string $plainKey): string;

    /** کلید با hash فعلی، یا hash قبلی در مهلت چرخش؛ بدون توجه به وضعیت (بررسی وضعیت با سرویس) */
    public function findByHash(string $hash): ?ApiKeyRecord;

    public function find(int $id, int $branchId): ?ApiKeyRecord;

    /** @return array<int, ApiKeyRecord> جدیدترین اول */
    public function listForBranch(int $branchId): array;

    /** تعداد کلیدهای باطل‌نشده‌ی شعبه */
    public function countActive(int $branchId): int;

    /**
     * دامنه‌هایی از این فهرست که روی کلید دیگری ثبت‌اند.
     *
     * @param  array<int, string>  $origins
     * @return array<int, string>
     */
    public function takenOrigins(array $origins, ?int $exceptKeyId = null): array;

    /**
     * @param  array<string, mixed>  $attributes  ستون‌های api_keys
     * @param  array<int, int>  $categoryIds  شناسه‌های دسته‌ها
     * @param  array<int, string>  $origins
     */
    public function create(array $attributes, array $categoryIds, array $origins): ApiKeyRecord;

    /**
     * ویرایش؛ $categoryIds/$origins اگر null باشند دست نمی‌خورند.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>|null  $categoryIds
     * @param  array<int, string>|null  $origins
     */
    public function update(int $id, array $attributes, ?array $categoryIds = null, ?array $origins = null): ApiKeyRecord;

    /** ثبت آخرین استفاده (بدون عوض‌کردن updated_at) */
    public function touch(int $id, ?string $ip, DateTimeInterface $at): void;

    /** کلیدهای وصل‌شده به یک دسته (برای پاک‌کردن کششان بعد از ویرایش دسته) */
    public function listByCategory(int $categoryId): array;
}
