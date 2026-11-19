<?php

namespace App\Services\SystemLog\Application\Contracts;

use App\Services\SystemLog\Application\DTOs\ErrorRecord;
use App\Services\SystemLog\Application\DTOs\Page;
use App\Services\SystemLog\Domain\Access\AccessScope;
use App\Services\SystemLog\Domain\Error\ErrorEntry;
use App\Services\SystemLog\Domain\Error\ErrorStatus;

/** پورت ذخیره‌سازی خطاها (پیاده‌سازی: EloquentErrorStore). */
interface ErrorStore
{
    /**
     * ثبت اتمیک یک وقوع. اگر خطایی با همین fingerprint بود، شمارنده بالا می‌رود و عکس «آخرین وقوع» جایگزین می‌شود
     * (خطای resolved دوباره new می‌شود)؛ وگرنه ردیف جدید. هویت خطا (کلاس، پیام، فایل، خط، level، type) فقط با اولین وقوع
     * ثبت می‌شود. نتیجه بدون stack/details است.
     */
    public function record(ErrorEntry $entry, string $fingerprint, bool $refreshSnapshot = true): ErrorRecord;

    /** جزئیات کامل (با stack و details). با scope فقط اگر خطا در محدوده‌ی آن باشد؛ بدون scope برای کار داخلی. */
    public function find(int $id, ?AccessScope $scope = null): ?ErrorRecord;

    /**
     * لیست خطاها فقط در محدوده‌ی scope (همه‌ی شعبه‌ها)، جدیدترین (last_seen_at) اول، بدون stack/details.
     * فیلترها فقط محدودتر می‌کنند: status (عدد)، source و type (شناسه‌ی دسته)، branch_id، from، to (DateTimeInterface)، text.
     *
     * @param  array<string, mixed>  $filters
     * @return Page<ErrorRecord>
     */
    public function search(array $filters, AccessScope $scope, int $page, int $perPage): Page;

    /** تغییر وضعیت و یادداشت. false اگر خطا وجود نداشته باشد. بررسی دسترسی کار لایه‌ی بالاتر است. */
    public function updateStatus(int $id, ErrorStatus $status, ?string $note, ?int $operatorId): bool;

    /** گرفتن «حق ارسال اعلان» با UPDATE شرطی (notified_at IS NULL)؛ فقط یک پروسه true می‌گیرد. */
    public function claimNotification(int $id): bool;

    /**
     * حذف خطاهای «حل‌شده» که resolved_at‌شان قبل از $before است (chunk به chunk). با $dryRun فقط می‌شمارد.
     * new/investigating/ignored هرگز حذف نمی‌شوند. تعداد حذف‌شده (یا قابل‌حذف) را برمی‌گرداند.
     */
    public function pruneResolved(\DateTimeInterface $before, bool $dryRun = false): int;
}
